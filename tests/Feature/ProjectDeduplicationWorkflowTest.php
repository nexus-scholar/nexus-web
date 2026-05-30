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
use App\Models\Project;
use App\Models\ProjectCorpusDedupRun;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProjectDeduplicationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_run_deduplication_and_review_duplicate_clusters(): void
    {
        [$project, $owner] = $this->draftCorpusProject();
        $ids = $this->seedDraftCorpus($project);

        $this->actingAs($owner)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('projects.deduplication.index', $project))
            ->assertOk()
            ->assertJsonPath('component', 'projects/deduplication')
            ->assertJsonPath('props.deduplication.state', 'not_run')
            ->assertJsonPath('props.deduplication.summary.draft_unique_works', 3)
            ->assertJsonPath('props.can.deduplicate_corpus', true)
            ->assertJsonPath('props.can.lock_corpus', true);

        $this->actingAs($owner)
            ->post(route('projects.corpus.deduplicate', $project))
            ->assertRedirect(route('projects.deduplication.index', $project, absolute: false))
            ->assertSessionHasNoErrors();

        $run = ProjectCorpusDedupRun::query()->firstOrFail();
        $clusterId = DB::table('dedup_clusters')->value('id');

        $this->assertSame(3, $run->input_count);
        $this->assertSame(2, $run->representative_count);
        $this->assertSame(1, $run->duplicate_cluster_count);
        $this->assertSame(1, $run->duplicates_removed);
        $this->assertSame(1, $run->policy_stats['persisted_doi_match']);

        $this->assertDatabaseHas('dedup_clusters', [
            'id' => $clusterId,
            'project_id' => $project->id,
            'representative_work_id' => $ids['work_1'],
            'cluster_size' => 2,
        ]);
        $this->assertDatabaseHas('cluster_members', [
            'cluster_id' => $clusterId,
            'work_id' => $ids['work_1'],
            'is_representative' => true,
        ]);
        $this->assertDatabaseHas('cluster_members', [
            'cluster_id' => $clusterId,
            'work_id' => $ids['work_2'],
            'is_representative' => false,
        ]);

        $this->actingAs($owner)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('projects.deduplication.index', [$project, 'cluster' => $clusterId]))
            ->assertOk()
            ->assertJsonPath('props.deduplication.state', 'duplicates_found')
            ->assertJsonPath('props.deduplication.latest_run.fresh', true)
            ->assertJsonPath('props.deduplication.clusters.0.id', $clusterId)
            ->assertJsonPath('props.deduplication.selectedCluster.members.0.work_id', $ids['work_1']);
    }

    public function test_reviewer_can_view_deduplication_but_cannot_mutate_it(): void
    {
        [$project] = $this->draftCorpusProject();
        $this->seedDraftCorpus($project);

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
            ->withHeaders($this->inertiaHeaders())
            ->get(route('projects.deduplication.index', $project))
            ->assertOk()
            ->assertJsonPath('props.can.view_deduplication', true)
            ->assertJsonPath('props.can.deduplicate_corpus', false)
            ->assertJsonPath('props.can.lock_corpus', false);

        $this->actingAs($reviewer)
            ->post(route('projects.corpus.deduplicate', $project))
            ->assertForbidden();

        $this->actingAs($reviewer)
            ->post(route('projects.corpus.lock', $project), [
                'reason' => 'Reviewer attempted to lock corpus.',
            ])
            ->assertForbidden();
    }

    public function test_corpus_lock_requires_a_fresh_deduplication_run(): void
    {
        [$project, $owner] = $this->draftCorpusProject();
        $ids = $this->seedDraftCorpus($project);

        $this->actingAs($owner)
            ->from(route('projects.deduplication.index', $project))
            ->post(route('projects.corpus.lock', $project), [
                'reason' => 'The team reviewed duplicate clusters for lock.',
            ])
            ->assertRedirect(route('projects.deduplication.index', $project, absolute: false))
            ->assertSessionHasErrors('corpus_lock');

        $this->actingAs($owner)
            ->post(route('projects.corpus.deduplicate', $project))
            ->assertSessionHasNoErrors();

        $queryId = $ids['query_1'];
        $newWork = (string) Str::uuid();
        DB::table('scholarly_works')->insert($this->work(
            $newWork,
            'Newly added cardiometabolic coaching record',
            'Added after deduplication.',
            2026,
        ));
        DB::table('query_works')->insert($this->queryWork($queryId, $newWork, 'openalex', 'W-NEW', 99));

        $this->actingAs($owner)
            ->from(route('projects.deduplication.index', $project))
            ->post(route('projects.corpus.lock', $project), [
                'reason' => 'The team reviewed duplicate clusters for lock.',
            ])
            ->assertRedirect(route('projects.deduplication.index', $project, absolute: false))
            ->assertSessionHasErrors('corpus_lock');
    }

    public function test_work_metadata_changes_make_latest_deduplication_run_stale(): void
    {
        [$project, $owner] = $this->draftCorpusProject();
        $ids = $this->seedDraftCorpus($project);

        $this->actingAs($owner)
            ->post(route('projects.corpus.deduplicate', $project))
            ->assertSessionHasNoErrors();

        DB::table('scholarly_works')
            ->where('id', $ids['work_2'])
            ->update([
                'title' => 'Digital coaching for cardiometabolic risk in community care',
                'updated_at' => now(),
            ]);

        $this->actingAs($owner)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('projects.deduplication.index', $project))
            ->assertOk()
            ->assertJsonPath('props.deduplication.state', 'stale')
            ->assertJsonPath('props.deduplication.latest_run.fresh', false)
            ->assertJsonPath('props.deduplication.lock.available', false)
            ->assertJsonPath('props.deduplication.lock.blocked_reason', 'Draft corpus changed since the latest deduplication run.');

        $this->actingAs($owner)
            ->from(route('projects.deduplication.index', $project))
            ->post(route('projects.corpus.lock', $project), [
                'reason' => 'The team reviewed duplicate clusters for lock.',
            ])
            ->assertRedirect(route('projects.deduplication.index', $project, absolute: false))
            ->assertSessionHasErrors('corpus_lock');
    }

    public function test_corpus_lock_refuses_incomplete_deduplication_evidence(): void
    {
        [$project, $owner] = $this->draftCorpusProject();
        $this->seedDraftCorpus($project);

        $this->actingAs($owner)
            ->post(route('projects.corpus.deduplicate', $project))
            ->assertSessionHasNoErrors();

        DB::table('cluster_members')
            ->where('is_representative', false)
            ->delete();

        $this->actingAs($owner)
            ->from(route('projects.deduplication.index', $project))
            ->post(route('projects.corpus.lock', $project), [
                'reason' => 'The team reviewed duplicate clusters for lock.',
            ])
            ->assertRedirect(route('projects.deduplication.index', $project, absolute: false))
            ->assertSessionHasErrors('corpus_lock');

        $this->assertDatabaseCount('corpus_snapshots', 0);
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'status' => ProjectStatus::DraftCorpus->value,
        ]);
    }

    public function test_lock_creates_representative_snapshot_and_blocks_later_search_mutation(): void
    {
        [$project, $owner] = $this->draftCorpusProject();
        $ids = $this->seedDraftCorpus($project);

        $this->actingAs($owner)
            ->post(route('projects.corpus.deduplicate', $project))
            ->assertSessionHasNoErrors();

        $this->actingAs($owner)
            ->post(route('projects.corpus.lock', $project), [
                'reason' => 'Deduplication reviewed and corpus approved for screening.',
            ])
            ->assertRedirect(route('projects.corpus.index', $project, absolute: false))
            ->assertSessionHasNoErrors();

        $project->refresh();
        $snapshotId = DB::table('corpus_snapshots')->value('id');

        $this->assertSame(ProjectStatus::LockedCorpus, $project->status);
        $this->assertNotNull($project->locked_at);
        $this->assertDatabaseHas('corpus_snapshots', [
            'id' => $snapshotId,
            'project_id' => $project->id,
            'work_count' => 2,
            'lock_reason' => 'Deduplication reviewed and corpus approved for screening.',
        ]);
        $this->assertDatabaseHas('corpus_snapshot_works', [
            'snapshot_id' => $snapshotId,
            'work_id' => $ids['work_1'],
        ]);
        $this->assertDatabaseMissing('corpus_snapshot_works', [
            'snapshot_id' => $snapshotId,
            'work_id' => $ids['work_2'],
        ]);
        $this->assertDatabaseHas('audit_events', [
            'project_id' => $project->id,
            'event_type' => 'project.corpus.locked',
            'reason' => 'Deduplication reviewed and corpus approved for screening.',
        ]);

        $this->actingAs($owner)
            ->patch(route('projects.search-plan.update', $project), [])
            ->assertForbidden();

        $this->actingAs($owner)
            ->post(route('projects.corpus.deduplicate', $project))
            ->assertForbidden();
    }

    /**
     * @return array{0: Project, 1: User}
     */
    private function draftCorpusProject(): array
    {
        [$workspace, $owner] = $this->sharedWorkspace();
        $project = app(CreateProject::class)->handle($workspace, $owner, 'Dedup workflow test', ReviewType::SystematicReview);
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
    private function seedDraftCorpus(Project $project): array
    {
        $query = (string) Str::uuid();
        $work1 = (string) Str::uuid();
        $work2 = (string) Str::uuid();
        $work3 = (string) Str::uuid();

        DB::table('search_queries')->insert($this->searchQuery($query, $project));
        DB::table('scholarly_works')->insert([
            $this->work($work1, 'Digital coaching for cardiometabolic risk in primary care', 'A complete abstract for the representative record.', 2024, 42),
            $this->work($work2, 'Digital coaching for cardiometabolic risk in primary care', null, 2024, 9),
            $this->work($work3, 'Remote monitoring for metabolic syndrome follow-up', 'An unrelated record.', 2022, 12),
        ]);
        DB::table('work_external_ids')->insert([
            $this->identifier($work1, 'doi', '10.1000/nexus.same', true),
            $this->identifier($work2, 'doi', 'https://doi.org/10.1000/nexus.same', true),
            $this->identifier($work3, 'openalex', 'W-UNRELATED', true),
        ]);
        DB::table('work_providers')->insert([
            $this->provider($work1, 'openalex', 'W-ONE'),
            $this->provider($work2, 'crossref', '10.1000/nexus.same'),
            $this->provider($work3, 'pubmed', '39000003'),
        ]);
        DB::table('query_works')->insert([
            $this->queryWork($query, $work1, 'openalex', 'W-ONE', 1),
            $this->queryWork($query, $work2, 'crossref', '10.1000/nexus.same', 2),
            $this->queryWork($query, $work3, 'pubmed', '39000003', 3),
        ]);

        return [
            'query_1' => $query,
            'work_1' => $work1,
            'work_2' => $work2,
            'work_3' => $work3,
        ];
    }

    private function searchQuery(string $id, Project $project): array
    {
        return [
            'id' => $id,
            'project_id' => $project->id,
            'query_text' => 'digital coaching cardiometabolic primary care',
            'from_year' => 2020,
            'to_year' => 2026,
            'max_results' => 75,
            'offset' => 0,
            'include_raw_data' => false,
            'provider_aliases' => json_encode(['openalex', 'crossref', 'pubmed']),
            'cache_key' => hash('sha256', $id),
            'status' => 'completed',
            'total_raw' => 3,
            'total_unique' => 3,
            'duration_ms' => 20,
            'executed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function work(string $id, string $title, ?string $abstract, int $year, int $citations = 0): array
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
            'cited_by_count' => $citations,
            'is_retracted' => false,
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

    private function inertiaHeaders(): array
    {
        $manifest = public_path('build/manifest.json');

        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => file_exists($manifest) ? hash_file('xxh128', $manifest) : '',
        ];
    }
}
