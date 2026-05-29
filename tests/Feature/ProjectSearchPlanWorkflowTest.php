<?php

namespace Tests\Feature;

use App\Actions\Projects\CreateProject;
use App\Actions\Workspaces\CreatePersonalWorkspace;
use App\Actions\Workspaces\CreateSharedWorkspace;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\ReviewType;
use App\Enums\WorkspaceMembershipStatus;
use App\Enums\WorkspaceRole;
use App\Jobs\RunProjectSearchPlanJob;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Nexus\Search\Application\Aggregator\AggregatedResult;
use Nexus\Search\Application\Aggregator\ProviderStat;
use Nexus\Search\Application\Port\SearchExecutorPort;
use Nexus\Search\Application\UseCase\SearchAcrossProviders;
use Nexus\Shared\Domain\CorpusSlice;
use Nexus\Shared\Port\JobLifecycleRecorderPort;
use Tests\TestCase;

class ProjectSearchPlanWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_and_update_completed_project_search_plan(): void
    {
        [$project, $owner] = $this->completedProject();

        $this->actingAs($owner)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('projects.search-plan.edit', $project))
            ->assertJsonPath('component', 'projects/search-plan')
            ->assertJsonPath('props.searchPlan.default_providers', 'openalex, crossref')
            ->assertJsonPath('props.can.update_search_plan', true);

        $this->actingAs($owner)
            ->from(route('projects.search-plan.edit', $project))
            ->patch(route('projects.search-plan.update', $project), [
                'default_providers' => 'openalex, semantic-scholar, pubmed',
                'default_year_from' => 2020,
                'default_year_to' => 2026,
                'default_result_limit' => 75,
                'include_raw_data' => false,
                'queries' => [
                    [
                        'query_key' => 'primary-search',
                        'label' => 'Primary provider search',
                        'query' => '(digital OR mobile) AND primary care',
                        'providers' => 'openalex, pubmed',
                        'year_from' => 2020,
                        'year_to' => 2026,
                        'result_limit' => 75,
                        'include_raw_data' => true,
                    ],
                    [
                        'query_key' => 'supplementary-search',
                        'label' => 'Supplementary citation search',
                        'query' => 'cardiometabolic intervention adherence',
                        'providers' => 'crossref',
                        'year_from' => null,
                        'year_to' => null,
                        'result_limit' => 25,
                        'include_raw_data' => false,
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $plan = $project->searchPlan()->firstOrFail()->load('queries');

        $this->assertSame(['openalex', 'semantic_scholar', 'pubmed'], $plan->default_providers);
        $this->assertSame(2, $plan->queries->count());
        $this->assertSame(['openalex', 'pubmed'], $plan->queries->first()->providers);
        $this->assertTrue($plan->queries->first()->include_raw_data);
        $this->assertDatabaseHas('audit_events', [
            'project_id' => $project->id,
            'event_type' => 'project.search_plan.updated',
        ]);
    }

    public function test_search_plan_route_is_blocked_until_protocol_is_complete(): void
    {
        $user = User::factory()->create();
        $workspace = app(CreatePersonalWorkspace::class)->handle($user);
        $project = app(CreateProject::class)->handle($workspace, $user, 'Draft search gate', ReviewType::SystematicReview);

        $this->actingAs($user)
            ->get(route('projects.search-plan.edit', $project))
            ->assertRedirect(route('projects.show', $project, absolute: false));
    }

    public function test_reviewer_and_viewer_can_read_but_not_edit_search_plan(): void
    {
        [$project] = $this->completedProject();

        foreach ([ProjectRole::Reviewer, ProjectRole::Viewer] as $role) {
            $member = User::factory()->create();
            app(CreatePersonalWorkspace::class)->handle($member);
            $this->workspaceMember($project->workspace, $member, WorkspaceRole::Member);
            $project->memberships()->create([
                'user_id' => $member->id,
                'role' => $role,
                'status' => 'active',
                'joined_at' => now(),
            ]);
            $member->forceFill(['current_workspace_id' => $project->workspace_id])->save();

            $this->actingAs($member)
                ->withHeaders($this->inertiaHeaders())
                ->get(route('projects.search-plan.edit', $project))
                ->assertJsonPath('component', 'projects/search-plan')
                ->assertJsonPath('props.can.update_search_plan', false);

            $this->actingAs($member)
                ->patch(route('projects.search-plan.update', $project), $this->searchPlanPayload())
                ->assertForbidden();
        }
    }

    public function test_workspace_admin_can_update_search_plan_without_project_membership(): void
    {
        [$project] = $this->completedProject();
        $admin = User::factory()->create();
        app(CreatePersonalWorkspace::class)->handle($admin);
        $this->workspaceMember($project->workspace, $admin, WorkspaceRole::Admin);
        $admin->forceFill(['current_workspace_id' => $project->workspace_id])->save();

        $this->actingAs($admin)
            ->patch(route('projects.search-plan.update', $project), $this->searchPlanPayload([
                'default_result_limit' => 100,
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(100, $project->searchPlan()->firstOrFail()->default_result_limit);
    }

    public function test_locked_project_blocks_search_plan_updates(): void
    {
        [$project, $owner] = $this->completedProject();
        $project->forceFill([
            'status' => ProjectStatus::LockedCorpus,
            'locked_at' => now(),
            'lock_reason' => 'Corpus locked for audit.',
        ])->save();

        $this->actingAs($owner)
            ->patch(route('projects.search-plan.update', $project), $this->searchPlanPayload())
            ->assertForbidden();
    }

    public function test_search_plan_provider_aliases_are_limited(): void
    {
        [$project, $owner] = $this->completedProject();

        $this->actingAs($owner)
            ->from(route('projects.search-plan.edit', $project))
            ->patch(route('projects.search-plan.update', $project), $this->searchPlanPayload([
                'default_providers' => 'openalex, unknown-provider',
            ]))
            ->assertRedirect(route('projects.search-plan.edit', $project, absolute: false))
            ->assertSessionHasErrors('default_providers');
    }

    public function test_owner_can_dispatch_search_run_from_saved_plan(): void
    {
        Bus::fake();
        [$project, $owner] = $this->completedProject();

        $this->actingAs($owner)
            ->post(route('projects.search-runs.store', $project))
            ->assertRedirect();

        $run = $project->searchRuns()->firstOrFail();

        $this->assertSame('queued', $run->status->value);
        $this->assertSame(ProjectStatus::Searching, $project->fresh()->status);
        $this->assertSame(1, $run->items()->count());
        $this->assertDatabaseHas('audit_events', [
            'project_id' => $project->id,
            'event_type' => 'project.search_run.dispatched',
        ]);
        Bus::assertDispatched(
            RunProjectSearchPlanJob::class,
            fn (RunProjectSearchPlanJob $job): bool => $job->searchRunId === $run->id,
        );
    }

    public function test_reviewer_cannot_dispatch_search_run(): void
    {
        [$project] = $this->completedProject();
        $reviewer = User::factory()->create();
        app(CreatePersonalWorkspace::class)->handle($reviewer);
        $this->workspaceMember($project->workspace, $reviewer, WorkspaceRole::Member);
        $project->memberships()->create([
            'user_id' => $reviewer->id,
            'role' => ProjectRole::Reviewer,
            'status' => 'active',
            'joined_at' => now(),
        ]);
        $reviewer->forceFill(['current_workspace_id' => $project->workspace_id])->save();

        $this->actingAs($reviewer)
            ->post(route('projects.search-runs.store', $project))
            ->assertForbidden();
    }

    public function test_search_run_job_executes_plan_items_through_core_port(): void
    {
        Bus::fake();
        [$project, $owner] = $this->completedProject();

        $this->app->instance(SearchExecutorPort::class, new class implements SearchExecutorPort
        {
            public function handle(SearchAcrossProviders $command): AggregatedResult
            {
                return new AggregatedResult(
                    corpus: CorpusSlice::empty(),
                    providerStats: [new ProviderStat('openalex', 3, 12)],
                    totalRaw: 3,
                    durationMs: 18,
                );
            }
        });

        $this->actingAs($owner)->post(route('projects.search-runs.store', $project));
        $run = $project->searchRuns()->firstOrFail();

        (new RunProjectSearchPlanJob($run->id))->handle(
            $this->app->make(SearchExecutorPort::class),
            $this->app->make(JobLifecycleRecorderPort::class),
        );

        $run->refresh()->load('items');

        $this->assertSame('completed', $run->status->value);
        $this->assertSame(ProjectStatus::DraftCorpus, $project->fresh()->status);
        $this->assertSame(3, $run->total_raw);
        $this->assertSame(0, $run->total_unique);
        $this->assertSame('completed', $run->items->first()->status->value);
        $this->assertNotNull($run->items->first()->core_search_query_id);
        $this->assertDatabaseHas('job_lifecycle_records', [
            'run_id' => $run->id,
            'status' => 'completed',
            'project_id' => $project->id,
        ]);
    }

    public function test_search_run_page_exposes_item_and_provider_progress(): void
    {
        Bus::fake();
        [$project, $owner] = $this->completedProject();

        $this->actingAs($owner)->post(route('projects.search-runs.store', $project));
        $run = $project->searchRuns()->firstOrFail();
        $item = $run->items()->firstOrFail();
        $item->update([
            'status' => 'completed',
            'core_search_query_id' => 'Qdemo',
            'total_raw' => 5,
            'total_unique' => 4,
        ]);
        $run->update([
            'status' => 'completed',
            'total_raw' => 5,
            'total_unique' => 4,
        ]);

        DB::table('search_queries')->insert([
            'id' => 'Qdemo',
            'project_id' => $project->id,
            'query_text' => 'primary care intervention',
            'max_results' => 50,
            'offset' => 0,
            'include_raw_data' => false,
            'provider_aliases' => json_encode(['openalex']),
            'cache_key' => str_repeat('a', 64),
            'status' => 'completed',
            'total_raw' => 5,
            'total_unique' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('search_query_providers')->insert([
            'id' => (string) Str::uuid(),
            'search_query_id' => 'Qdemo',
            'provider_alias' => 'openalex',
            'status' => 'completed',
            'result_count' => 5,
            'total_raw' => 5,
            'total_unique' => 4,
            'duration_ms' => 12,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($owner)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('projects.search-runs.show', [$project, $run]))
            ->assertJsonPath('component', 'projects/search-run')
            ->assertJsonPath('props.searchRun.status', 'completed')
            ->assertJsonPath('props.searchRun.items.0.provider_progress.0.provider_alias', 'openalex')
            ->assertJsonPath('props.searchRun.items.0.provider_progress.0.total_unique', 4);
    }

    /**
     * @return array{0: Project, 1: User}
     */
    private function completedProject(): array
    {
        [$workspace, $owner] = $this->sharedWorkspace();
        $project = app(CreateProject::class)->handle($workspace, $owner, 'Completed search plan review', ReviewType::SystematicReview);

        $this->actingAs($owner)
            ->patch(route('projects.protocol.update', $project), $this->completeProtocolPayload([
                'intent' => 'complete',
                'title' => 'Completed search plan review',
            ]))
            ->assertSessionHasNoErrors();

        return [$project->refresh()->load('workspace'), $owner];
    }

    /**
     * @return array{0: Workspace, 1: User}
     */
    private function sharedWorkspace(): array
    {
        $owner = User::factory()->create();
        app(CreatePersonalWorkspace::class)->handle($owner);
        $workspace = app(CreateSharedWorkspace::class)->handle($owner, 'Evidence Synthesis Lab');
        $owner->forceFill(['current_workspace_id' => $workspace->id])->save();

        return [$workspace, $owner];
    }

    private function workspaceMember(Workspace $workspace, User $user, WorkspaceRole $role): void
    {
        $workspace->memberships()->create([
            'user_id' => $user->id,
            'role' => $role,
            'status' => WorkspaceMembershipStatus::Active,
            'joined_at' => now(),
        ]);
    }

    private function completeProtocolPayload(array $overrides = []): array
    {
        return [
            'intent' => 'save',
            'title' => 'Complete protocol',
            'review_type' => ReviewType::SystematicReview->value,
            'research_question' => 'What evidence supports this intervention?',
            'background' => 'The team needs a defensible protocol before search.',
            'inclusion_criteria' => 'Peer-reviewed empirical studies.',
            'exclusion_criteria' => 'Editorials and non-human studies.',
            'target_providers' => 'openalex, crossref',
            'date_range_start' => null,
            'date_range_end' => null,
            'no_date_limit' => true,
            'language_policy' => 'English-language records.',
            'min_reviewer_count' => 2,
            'ai_screening_policy' => 'human_only',
            'full_text_policy' => 'optional',
            ...$overrides,
        ];
    }

    private function searchPlanPayload(array $overrides = []): array
    {
        return [
            'default_providers' => 'openalex, crossref',
            'default_year_from' => null,
            'default_year_to' => null,
            'default_result_limit' => 50,
            'include_raw_data' => false,
            'queries' => [
                [
                    'query_key' => 'primary-search',
                    'label' => 'Primary search',
                    'query' => 'primary care intervention',
                    'providers' => 'openalex, crossref',
                    'year_from' => null,
                    'year_to' => null,
                    'result_limit' => 50,
                    'include_raw_data' => false,
                ],
            ],
            ...$overrides,
        ];
    }

    private function inertiaHeaders(): array
    {
        $manifest = public_path('build/manifest.json');

        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => file_exists($manifest) ? hash_file('xxh128', $manifest) : '',
        ];
    }
}
