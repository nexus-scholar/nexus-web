<?php

namespace Tests\Feature;

use App\Actions\Projects\CreateProject;
use App\Actions\Workspaces\CreatePersonalWorkspace;
use App\Actions\Workspaces\CreateSharedWorkspace;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\ReviewType;
use App\Enums\SearchRunStatus;
use App\Enums\WorkspaceMembershipStatus;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\ProjectSearchPlan;
use App\Models\ProjectSearchRun;
use App\Models\ProjectSearchRunItem;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProjectCorpusWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_draft_corpus_metrics_records_and_provenance(): void
    {
        [$project, $owner] = $this->draftCorpusProject();
        $ids = $this->seedDraftCorpus($project, $owner);

        $this->actingAs($owner)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('projects.corpus.index', $project))
            ->assertOk()
            ->assertJsonPath('component', 'projects/corpus')
            ->assertJsonPath('props.corpus.source', 'draft')
            ->assertJsonPath('props.corpus.metrics.unique_works', 3)
            ->assertJsonPath('props.corpus.metrics.raw_query_links', 4)
            ->assertJsonPath('props.corpus.metrics.search_queries', 2)
            ->assertJsonPath('props.corpus.metrics.missing_abstracts', 1)
            ->assertJsonPath('props.corpus.metrics.missing_identifiers', 1)
            ->assertJsonPath('props.corpus.metrics.retracted_records', 1)
            ->assertJsonPath('props.corpus.metrics.duplicate_clusters', 1)
            ->assertJsonPath('props.corpus.selectedRecord', null)
            ->assertJsonPath('props.corpus.records.data.0.provenance', [])
            ->assertJsonPath('props.can.view_corpus', true);

        $this->actingAs($owner)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('projects.corpus.index', [$project, 'work' => $ids['work_3']]))
            ->assertOk()
            ->assertJsonPath('props.corpus.selectedRecord.id', $ids['work_3'])
            ->assertJsonPath('props.corpus.selectedRecord.provenance.0.provider_alias', 'semantic_scholar');
    }

    public function test_reviewer_and_viewer_can_read_corpus_but_unrelated_users_cannot(): void
    {
        [$project, $owner] = $this->draftCorpusProject();
        $this->seedDraftCorpus($project, $owner);

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
                ->get(route('projects.corpus.index', $project))
                ->assertOk()
                ->assertJsonPath('props.can.view_corpus', true)
                ->assertJsonMissingPath('props.can.lock_corpus')
                ->assertJsonMissingPath('props.can.deduplicate_corpus');
        }

        $outsider = User::factory()->create();
        app(CreatePersonalWorkspace::class)->handle($outsider);

        $this->actingAs($outsider)
            ->get(route('projects.corpus.index', $project))
            ->assertForbidden();
    }

    public function test_suspended_workspace_blocks_corpus_review(): void
    {
        [$project, $owner] = $this->draftCorpusProject();
        $this->seedDraftCorpus($project, $owner);

        $project->workspace->forceFill([
            'suspended_at' => now(),
            'suspended_by' => $owner->id,
            'suspended_reason' => 'Suspended for audit.',
        ])->save();

        $this->actingAs($owner)
            ->get(route('projects.corpus.index', $project))
            ->assertForbidden();
    }

    public function test_empty_project_returns_empty_corpus_state(): void
    {
        [$project, $owner] = $this->draftCorpusProject();

        $this->actingAs($owner)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('projects.corpus.index', $project))
            ->assertOk()
            ->assertJsonPath('props.corpus.metrics.unique_works', 0)
            ->assertJsonPath('props.corpus.records.meta.total', 0)
            ->assertJsonPath('props.corpus.selectedRecord', null);
    }

    public function test_locked_project_uses_latest_corpus_snapshot(): void
    {
        [$project, $owner] = $this->draftCorpusProject();
        $ids = $this->seedDraftCorpus($project, $owner);
        $project->forceFill([
            'status' => ProjectStatus::LockedCorpus,
            'locked_at' => now(),
            'locked_by' => (string) $owner->id,
            'lock_reason' => 'Ready for screening.',
        ])->save();

        $oldSnapshot = (string) Str::uuid();
        $latestSnapshot = (string) Str::uuid();

        DB::table('corpus_snapshots')->insert([
            [
                'id' => $oldSnapshot,
                'project_id' => $project->id,
                'locked_at' => now()->subDay(),
                'work_count' => 1,
                'created_by' => (string) $owner->id,
                'lock_reason' => 'Older snapshot.',
                'metadata' => json_encode(['version' => 1]),
                'created_at' => now()->subDay(),
                'updated_at' => now()->subDay(),
            ],
            [
                'id' => $latestSnapshot,
                'project_id' => $project->id,
                'locked_at' => now(),
                'work_count' => 2,
                'created_by' => (string) $owner->id,
                'lock_reason' => 'Ready for screening.',
                'metadata' => json_encode(['version' => 2]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->snapshotWork($oldSnapshot, $ids['work_1'], [$ids['query_1']], ['openalex']);
        $this->snapshotWork($latestSnapshot, $ids['work_1'], [$ids['query_1']], ['openalex', 'crossref']);
        $this->snapshotWork($latestSnapshot, $ids['work_2'], [$ids['query_1']], ['pubmed']);

        $this->actingAs($owner)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('projects.corpus.index', $project))
            ->assertOk()
            ->assertJsonPath('props.corpus.source', 'locked')
            ->assertJsonPath('props.corpus.snapshot.id', $latestSnapshot)
            ->assertJsonPath('props.corpus.metrics.unique_works', 2)
            ->assertJsonPath('props.corpus.records.meta.total', 2)
            ->assertJsonMissing(['id' => $ids['work_3']]);
    }

    public function test_filters_constrain_corpus_rows_on_the_server(): void
    {
        [$project, $owner] = $this->draftCorpusProject();
        $ids = $this->seedDraftCorpus($project, $owner);

        $this->actingAs($owner)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('projects.corpus.index', [$project, 'provider' => 'pubmed']))
            ->assertJsonPath('props.corpus.records.meta.total', 1)
            ->assertJsonPath('props.corpus.records.data.0.id', $ids['work_2']);

        $this->actingAs($owner)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('projects.corpus.index', [$project, 'missing_identifier' => 1]))
            ->assertJsonPath('props.corpus.records.meta.total', 1)
            ->assertJsonPath('props.corpus.records.data.0.id', $ids['work_2']);

        $this->actingAs($owner)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('projects.corpus.index', [$project, 'duplicate_status' => 'in_cluster']))
            ->assertJsonPath('props.corpus.records.meta.total', 2);

        $this->actingAs($owner)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('projects.corpus.index', [$project, 'retracted' => 1]))
            ->assertJsonPath('props.corpus.records.meta.total', 1)
            ->assertJsonPath('props.corpus.records.data.0.id', $ids['work_3']);
    }

    public function test_sorting_is_server_owned_and_query_backed(): void
    {
        [$project, $owner] = $this->draftCorpusProject();
        $ids = $this->seedDraftCorpus($project, $owner);

        $this->actingAs($owner)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('projects.corpus.index', [$project, 'sort' => 'title', 'direction' => 'asc']))
            ->assertOk()
            ->assertJsonPath('props.corpus.filters.sort', 'title')
            ->assertJsonPath('props.corpus.filters.direction', 'asc')
            ->assertJsonPath('props.corpus.records.data.0.id', $ids['work_1']);

        $this->actingAs($owner)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('projects.corpus.index', [$project, 'sort' => 'year', 'direction' => 'asc']))
            ->assertOk()
            ->assertJsonPath('props.corpus.records.data.0.id', $ids['work_2']);
    }

    public function test_pagination_preserves_filter_query_parameters(): void
    {
        [$project, $owner] = $this->draftCorpusProject();
        $this->seedDraftCorpus($project, $owner);

        $response = $this->actingAs($owner)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('projects.corpus.index', [$project, 'year_from' => 2020, 'per_page' => 1]));

        $response
            ->assertJsonPath('props.corpus.records.meta.total', 3)
            ->assertJsonPath('props.corpus.records.meta.per_page', 1);

        $this->assertStringContainsString(
            'year_from=2020',
            (string) $response->json('props.corpus.records.links.next'),
        );
    }

    /**
     * @return array{0: Project, 1: User}
     */
    private function draftCorpusProject(): array
    {
        [$workspace, $owner] = $this->sharedWorkspace();
        $project = app(CreateProject::class)->handle($workspace, $owner, 'Corpus review test', ReviewType::SystematicReview);
        $project->forceFill(['status' => ProjectStatus::DraftCorpus])->save();

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

    /**
     * @return array<string, string>
     */
    private function seedDraftCorpus(Project $project, User $owner): array
    {
        $now = now();
        $query1 = (string) Str::uuid();
        $query2 = (string) Str::uuid();
        $work1 = (string) Str::uuid();
        $work2 = (string) Str::uuid();
        $work3 = (string) Str::uuid();

        $plan = ProjectSearchPlan::factory()->create([
            'project_id' => $project->id,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $run = ProjectSearchRun::factory()->create([
            'project_id' => $project->id,
            'project_search_plan_id' => $plan->id,
            'status' => SearchRunStatus::Completed,
            'query_count' => 2,
            'total_raw' => 4,
            'total_unique' => 3,
            'requested_by' => $owner->id,
            'completed_at' => $now,
        ]);

        ProjectSearchRunItem::factory()->create([
            'project_search_run_id' => $run->id,
            'project_search_plan_query_id' => null,
            'sort_order' => 1,
            'query_key' => 'primary-search',
            'label' => 'Primary intervention search',
            'query' => 'digital cardiometabolic primary care',
            'providers' => ['openalex', 'crossref', 'pubmed'],
            'status' => SearchRunStatus::Completed,
            'core_search_query_id' => $query1,
            'total_raw' => 3,
            'total_unique' => 2,
        ]);

        ProjectSearchRunItem::factory()->create([
            'project_search_run_id' => $run->id,
            'project_search_plan_query_id' => null,
            'sort_order' => 2,
            'query_key' => 'implementation-search',
            'label' => 'Implementation search',
            'query' => 'implementation adherence digital',
            'providers' => ['semantic_scholar'],
            'status' => SearchRunStatus::Completed,
            'core_search_query_id' => $query2,
            'total_raw' => 1,
            'total_unique' => 1,
        ]);

        DB::table('search_queries')->insert([
            $this->searchQuery($query1, $project, 'digital cardiometabolic primary care', ['openalex', 'crossref', 'pubmed']),
            $this->searchQuery($query2, $project, 'implementation adherence digital', ['semantic_scholar']),
        ]);

        DB::table('scholarly_works')->insert([
            $this->work($work1, 'Digital coaching for cardiometabolic risk in primary care', 'A complete abstract about digital coaching.', 2024, false),
            $this->work($work2, 'Mobile reminders for cardiometabolic medication adherence', null, 2022, false),
            $this->work($work3, 'Retracted telehealth intervention trial', 'Retracted trial abstract.', 2025, true),
        ]);

        DB::table('work_external_ids')->insert([
            $this->identifier($work1, 'doi', '10.1000/nexus.1', true),
            $this->identifier($work1, 'openalex', 'W-DEMO-1', false),
            $this->identifier($work3, 'pubmed', '39000001', true),
        ]);

        DB::table('work_providers')->insert([
            $this->provider($work1, 'openalex', 'W-DEMO-1'),
            $this->provider($work1, 'crossref', '10.1000/nexus.1'),
            $this->provider($work2, 'pubmed', '39000000'),
            $this->provider($work3, 'semantic_scholar', 'S2-DEMO-3'),
        ]);

        DB::table('query_works')->insert([
            $this->queryWork($query1, $work1, 'openalex', 'W-DEMO-1', 1),
            $this->queryWork($query1, $work1, 'crossref', '10.1000/nexus.1', 2),
            $this->queryWork($query1, $work2, 'pubmed', '39000000', 3),
            $this->queryWork($query2, $work3, 'semantic_scholar', 'S2-DEMO-3', 1),
        ]);

        $author = (string) Str::uuid();
        DB::table('authors')->insert([
            'id' => $author,
            'full_name' => 'Lina Haddad',
            'normalized_name' => 'lina haddad',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('work_authors')->insert([
            'id' => (string) Str::uuid(),
            'work_id' => $work1,
            'author_id' => $author,
            'position' => 1,
            'is_corresponding' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $cluster = (string) Str::uuid();
        DB::table('dedup_clusters')->insert([
            'id' => $cluster,
            'project_id' => $project->id,
            'strategy' => 'demo',
            'representative_work_id' => $work1,
            'cluster_size' => 2,
            'confidence' => 0.9400,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('cluster_members')->insert([
            $this->clusterMember($cluster, $work1, true),
            $this->clusterMember($cluster, $work2, false),
        ]);

        return [
            'query_1' => $query1,
            'query_2' => $query2,
            'work_1' => $work1,
            'work_2' => $work2,
            'work_3' => $work3,
        ];
    }

    private function searchQuery(string $id, Project $project, string $query, array $providers): array
    {
        return [
            'id' => $id,
            'project_id' => $project->id,
            'query_text' => $query,
            'from_year' => 2020,
            'to_year' => 2026,
            'max_results' => 75,
            'offset' => 0,
            'include_raw_data' => false,
            'provider_aliases' => json_encode($providers),
            'cache_key' => hash('sha256', $id),
            'status' => 'completed',
            'total_raw' => 2,
            'total_unique' => 2,
            'duration_ms' => 30,
            'executed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function work(string $id, string $title, ?string $abstract, int $year, bool $retracted): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'abstract' => $abstract,
            'year' => $year,
            'venue_name' => 'Journal of Demo Evidence',
            'venue_type' => 'journal',
            'url' => 'https://example.test/'.$id,
            'language' => 'en',
            'cited_by_count' => 12,
            'is_retracted' => $retracted,
            'retrieved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function identifier(string $workId, string $namespace, string $value, bool $primary): array
    {
        return [
            'id' => (string) Str::uuid(),
            'work_id' => $workId,
            'namespace' => $namespace,
            'value' => $value,
            'is_primary' => $primary,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function provider(string $workId, string $provider, string $providerWorkId): array
    {
        return [
            'id' => (string) Str::uuid(),
            'work_id' => $workId,
            'provider_alias' => $provider,
            'provider_work_id' => $providerWorkId,
            'metadata' => json_encode(['source' => 'test']),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function queryWork(string $queryId, string $workId, string $provider, string $providerWorkId, int $rank): array
    {
        return [
            'id' => (string) Str::uuid(),
            'search_query_id' => $queryId,
            'work_id' => $workId,
            'provider_alias' => $provider,
            'provider_work_id' => $providerWorkId,
            'rank' => $rank,
            'seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function clusterMember(string $cluster, string $work, bool $representative): array
    {
        return [
            'id' => (string) Str::uuid(),
            'cluster_id' => $cluster,
            'work_id' => $work,
            'is_representative' => $representative,
            'reason' => 'doi',
            'confidence' => 0.9400,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function snapshotWork(string $snapshot, string $work, array $queries, array $providers): void
    {
        DB::table('corpus_snapshot_works')->insert([
            'id' => (string) Str::uuid(),
            'snapshot_id' => $snapshot,
            'work_id' => $work,
            'search_query_ids' => json_encode($queries),
            'provider_aliases' => json_encode($providers),
            'provenance' => json_encode(collect($providers)
                ->map(fn (string $provider, int $index): array => [
                    'search_query_id' => $queries[0],
                    'provider_alias' => $provider,
                    'provider_work_id' => strtoupper($provider).'-SNAPSHOT',
                    'rank' => $index + 1,
                    'seen_at' => now()->toISOString(),
                ])
                ->all()),
            'included_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
