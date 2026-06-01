<?php

namespace Tests\Feature;

use App\Actions\Projects\BuildProjectFullTextCandidates;
use App\Actions\Projects\BuildProjectFullTextScreeningCandidates;
use App\Actions\Projects\CreateProject;
use App\Actions\Projects\RecordProjectFullTextScreeningDecision;
use App\Actions\Projects\RecordProjectScreeningDecision;
use App\Actions\Projects\RefreshProjectFullTextBatchCounts;
use App\Actions\Projects\ResolveProjectFullTextScreeningConflict;
use App\Actions\Projects\StartProjectFullTextBatch;
use App\Actions\Projects\StartProjectFullTextScreeningBatch;
use App\Actions\Projects\StartProjectScreeningBatch;
use App\Actions\Workspaces\CreatePersonalWorkspace;
use App\Actions\Workspaces\CreateSharedWorkspace;
use App\Enums\ProjectFullTextBatchStatus;
use App\Enums\ProjectFullTextItemStatus;
use App\Enums\ProjectMembershipStatus;
use App\Enums\ProjectRole;
use App\Enums\ProjectScreeningBatchStatus;
use App\Enums\ProjectStatus;
use App\Enums\ProtocolStatus;
use App\Enums\ReviewType;
use App\Enums\WorkspaceMembershipStatus;
use App\Enums\WorkspaceRole;
use App\Jobs\RunProjectFullTextBatchJob;
use App\Models\Project;
use App\Models\ProjectFullTextBatch;
use App\Models\ProjectProtocol;
use App\Models\ProjectScreeningAssignment;
use App\Models\ProjectScreeningBatch;
use App\Models\ProjectScreeningConflict;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Nexus\Dissemination\Application\UseCase\RetrieveFullTextHandler;
use Nexus\Dissemination\Domain\Port\DownloadResult;
use Nexus\Dissemination\Domain\Port\FullTextCandidateSourcePort;
use Nexus\Dissemination\Domain\Port\FullTextSourceCandidate;
use Nexus\Dissemination\Domain\Port\FullTextSourceCollection;
use Nexus\Dissemination\Domain\Port\PdfDownloaderPort;
use Nexus\Laravel\Persistence\Repository\EloquentWorkRepository;
use Nexus\Screening\Domain\ScreeningDecision;
use Nexus\Screening\Domain\ScreeningStage;
use Nexus\Search\Domain\Port\WorkRepositoryPort;
use Nexus\Shared\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkId;
use Tests\TestCase;

class ProjectFullTextWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidate_builder_uses_completed_screening_handoff_and_excludes_final_excludes(): void
    {
        [$project] = $this->completedScreeningProject([
            ScreeningDecision::INCLUDE,
            ScreeningDecision::NEEDS_REVIEW,
            ScreeningDecision::EXCLUDE,
        ]);

        $candidateSet = app(BuildProjectFullTextCandidates::class)->handle($project);

        $this->assertTrue($candidateSet['ready']);
        $this->assertSame(2, $candidateSet['counts']['candidate_count']);
        $this->assertSame(1, $candidateSet['counts']['include']);
        $this->assertSame(1, $candidateSet['counts']['needs_review']);
        $this->assertSame(1, $candidateSet['counts']['excluded']);
        $this->assertSame(
            [ScreeningDecision::INCLUDE->value, ScreeningDecision::NEEDS_REVIEW->value],
            collect($candidateSet['candidates'])->pluck('screening_decision')->all(),
        );
    }

    public function test_owner_can_view_and_queue_full_text_retrieval_from_route(): void
    {
        Queue::fake();
        [$project, $owner] = $this->completedScreeningProject([
            ScreeningDecision::INCLUDE,
            ScreeningDecision::NEEDS_REVIEW,
            ScreeningDecision::EXCLUDE,
        ]);

        $this->actingAs($owner)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('projects.full-text.index', $project))
            ->assertOk()
            ->assertJsonPath('component', 'projects/full-text')
            ->assertJsonPath('props.fullText.readiness.ready', true)
            ->assertJsonPath('props.fullText.readiness.counts.candidate_count', 2)
            ->assertJsonPath('props.can.manage_full_text', true);

        $this->actingAs($owner)
            ->post(route('projects.full-text.batches.store', $project))
            ->assertRedirect(route('projects.full-text.index', $project));

        Queue::assertPushed(RunProjectFullTextBatchJob::class);

        $batch = ProjectFullTextBatch::query()->where('project_id', $project->id)->firstOrFail();
        $this->assertSame(ProjectFullTextBatchStatus::Queued, $batch->status);
        $this->assertSame(2, $batch->candidate_count);
        $this->assertDatabaseCount('project_full_text_items', 2);
        $this->assertDatabaseHas('audit_events', [
            'project_id' => $project->id,
            'event_type' => 'project.full_text.batch_started',
            'target_id' => $batch->id,
        ]);
    }

    public function test_reviewer_and_viewer_can_inspect_but_cannot_start_retrieval(): void
    {
        [$project, , $reviewer, $viewer] = $this->completedScreeningProject([
            ScreeningDecision::INCLUDE,
            ScreeningDecision::NEEDS_REVIEW,
        ]);

        $this->actingAs($reviewer)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('projects.full-text.index', $project))
            ->assertOk()
            ->assertJsonPath('props.can.manage_full_text', false);

        $this->actingAs($viewer)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('projects.full-text.index', $project))
            ->assertOk()
            ->assertJsonPath('props.can.manage_full_text', false);

        $this->actingAs($reviewer)
            ->post(route('projects.full-text.batches.store', $project))
            ->assertForbidden();

        $this->assertDatabaseCount('project_full_text_batches', 0);
    }

    public function test_manual_upload_only_policy_blocks_automatic_retrieval(): void
    {
        [$project, $owner] = $this->completedScreeningProject([
            ScreeningDecision::INCLUDE,
        ]);
        $project->protocol->forceFill(['full_text_policy' => 'manual_uploads_only'])->save();

        $this->actingAs($owner)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('projects.full-text.index', $project))
            ->assertOk()
            ->assertJsonPath('props.fullText.readiness.ready', false)
            ->assertJsonPath(
                'props.fullText.readiness.blockers.0',
                'This protocol allows manual uploads only; automatic retrieval is disabled.',
            );

        $this->actingAs($owner)
            ->from(route('projects.full-text.index', $project))
            ->post(route('projects.full-text.batches.store', $project))
            ->assertRedirect(route('projects.full-text.index', $project))
            ->assertSessionHasErrors('full_text');

        $this->assertDatabaseCount('project_full_text_batches', 0);
    }

    public function test_retrieval_job_records_success_failure_skipped_items_and_source_audit(): void
    {
        Queue::fake();
        Storage::fake('public');
        [$project, $owner, , , $workIds] = $this->completedScreeningProject([
            ScreeningDecision::INCLUDE,
            ScreeningDecision::INCLUDE,
            ScreeningDecision::NEEDS_REVIEW,
            ScreeningDecision::EXCLUDE,
        ], [
            'Successful PDF candidate',
            'Bad PDF candidate',
            'Skipped repository candidate',
            'Excluded candidate',
        ]);
        $skippedWorkId = $workIds[2];

        $this->fakeFullTextCore($skippedWorkId);

        $batch = app(StartProjectFullTextBatch::class)->handle($project, $owner);

        app()->call([new RunProjectFullTextBatchJob($batch->id), 'handle']);

        $batch->refresh();
        $this->assertSame(ProjectFullTextBatchStatus::CompletedWithFailures, $batch->status);
        $this->assertSame(1, $batch->success_count);
        $this->assertSame(1, $batch->failed_count);
        $this->assertSame(1, $batch->skipped_count);

        $this->assertDatabaseHas('project_full_text_items', [
            'batch_id' => $batch->id,
            'work_id' => $workIds[0],
            'status' => ProjectFullTextItemStatus::Success->value,
            'source_alias' => 'demo_oa',
        ]);
        $this->assertDatabaseHas('project_full_text_items', [
            'batch_id' => $batch->id,
            'work_id' => $workIds[1],
            'status' => ProjectFullTextItemStatus::Failed->value,
            'source_alias' => 'demo_oa',
        ]);
        $this->assertDatabaseHas('project_full_text_items', [
            'batch_id' => $batch->id,
            'work_id' => $workIds[2],
            'status' => ProjectFullTextItemStatus::Skipped->value,
        ]);
        $this->assertDatabaseHas('pdf_fetches', [
            'work_id' => $workIds[0],
            'source_alias' => 'demo_oa',
            'status' => 'success',
        ]);
        $this->assertDatabaseHas('pdf_fetches', [
            'work_id' => $workIds[1],
            'source_alias' => 'demo_oa',
            'status' => 'failure',
        ]);

        $successItem = $batch->items()->where('work_id', $workIds[0])->firstOrFail();
        Storage::disk('public')->assertExists((string) $successItem->artifact_path);

        $this->actingAs($owner)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('projects.full-text.index', ['project' => $project, 'item' => $successItem->id]))
            ->assertOk()
            ->assertJsonPath('props.fullText.selectedItem.id', $successItem->id)
            ->assertJsonPath('props.fullText.selectedItem.source_attempts.0.status', 'success');
    }

    public function test_full_text_screening_candidate_builder_only_screens_successful_artifacts(): void
    {
        [$project, $owner, , , $workIds] = $this->completedScreeningProject([
            ScreeningDecision::INCLUDE,
            ScreeningDecision::NEEDS_REVIEW,
            ScreeningDecision::INCLUDE,
        ]);
        $batch = $this->completedRetrievalBatch($project, $owner, [
            $workIds[0] => ProjectFullTextItemStatus::Success,
            $workIds[1] => ProjectFullTextItemStatus::Failed,
            $workIds[2] => ProjectFullTextItemStatus::ManualNeeded,
        ]);

        $candidateSet = app(BuildProjectFullTextScreeningCandidates::class)->handle($project);

        $this->assertTrue($candidateSet['ready']);
        $this->assertSame(1, $candidateSet['counts']['screenable']);
        $this->assertSame(1, $candidateSet['follow_up']['failed']);
        $this->assertSame(1, $candidateSet['follow_up']['manual_needed']);
        $this->assertSame($workIds[0], $candidateSet['candidates'][0]['work_id']);
        $this->assertSame(
            $batch->items()->where('work_id', $workIds[0])->value('id'),
            $candidateSet['candidates'][0]['full_text_item']['id'],
        );
    }

    public function test_owner_can_start_full_text_screening_and_reviewer_records_full_text_decision(): void
    {
        [$project, $owner, $reviewer, , $workIds] = $this->completedScreeningProject([
            ScreeningDecision::INCLUDE,
            ScreeningDecision::NEEDS_REVIEW,
        ]);
        $this->completedRetrievalBatch($project, $owner, [
            $workIds[0] => ProjectFullTextItemStatus::Success,
            $workIds[1] => ProjectFullTextItemStatus::Success,
        ]);

        $this->actingAs($owner)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('projects.full-text-screening.index', $project))
            ->assertOk()
            ->assertJsonPath('component', 'projects/full-text-screening')
            ->assertJsonPath('props.screening.readiness.ready', true)
            ->assertJsonPath('props.screening.readiness.counts.screenable', 2)
            ->assertJsonPath('props.can.manage_full_text_screening', true);

        $this->actingAs($owner)
            ->post(route('projects.full-text-screening.batches.store', $project), [
                'name' => 'Full-text decisions',
                'required_reviewer_count' => 1,
                'reviewer_ids' => [$reviewer->id],
            ])
            ->assertRedirect(route('projects.full-text-screening.index', $project));

        $screeningBatch = ProjectScreeningBatch::query()
            ->where('project_id', $project->id)
            ->where('stage', ScreeningStage::FULL_TEXT->value)
            ->firstOrFail();
        $this->assertSame(2, $screeningBatch->assignments()->count());
        $this->assertDatabaseHas('screening_runs', [
            'id' => $screeningBatch->screening_run_id,
            'stage' => ScreeningStage::FULL_TEXT->value,
        ]);

        $assignment = $screeningBatch->assignments()->where('assigned_to', $reviewer->id)->firstOrFail();
        $this->assertNotNull($assignment->source_full_text_item_id);

        $this->actingAs($reviewer)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('projects.full-text-screening.queue', [
                'project' => $project,
                'assignment' => $assignment->id,
            ]))
            ->assertOk()
            ->assertJsonPath('component', 'projects/full-text-screening-queue')
            ->assertJsonPath('props.queue.selectedAssignment.artifact.status', 'success');

        $this->actingAs($reviewer)
            ->post(route('projects.full-text-screening.assignments.decision', [$project, $assignment]), [
                'decision' => ScreeningDecision::INCLUDE->value,
                'artifact_inspected' => '1',
                'reason' => 'The full text satisfies the eligibility criteria.',
                'evidence' => 'Methods and outcomes match the protocol.',
            ])
            ->assertRedirect();

        $assignment->refresh();
        $this->assertDatabaseHas('screening_decisions', [
            'id' => $assignment->screening_decision_id,
            'stage' => ScreeningStage::FULL_TEXT->value,
            'decision' => ScreeningDecision::INCLUDE->value,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'project_id' => $project->id,
            'event_type' => 'project.full_text_screening.decision_recorded',
            'target_id' => $assignment->id,
        ]);
    }

    public function test_owner_can_start_full_text_screening_as_only_reviewer(): void
    {
        [$project, $owner, , , $workIds] = $this->completedScreeningProject([
            ScreeningDecision::INCLUDE,
            ScreeningDecision::NEEDS_REVIEW,
        ]);
        $this->completedRetrievalBatch($project, $owner, [
            $workIds[0] => ProjectFullTextItemStatus::Success,
            $workIds[1] => ProjectFullTextItemStatus::Success,
        ]);

        $batch = app(StartProjectFullTextScreeningBatch::class)->handle(
            $project,
            $owner,
            [$owner->id],
            1,
            'Owner full-text screening',
        );

        $this->assertSame(ProjectScreeningBatchStatus::Active, $batch->status);
        $this->assertSame(2, $batch->assignments()->where('assigned_to', $owner->id)->count());
    }

    public function test_full_text_screening_does_not_assign_failed_items_and_exclude_requires_basis(): void
    {
        [$project, $owner, $reviewer, , $workIds] = $this->completedScreeningProject([
            ScreeningDecision::INCLUDE,
            ScreeningDecision::INCLUDE,
        ]);
        $this->completedRetrievalBatch($project, $owner, [
            $workIds[0] => ProjectFullTextItemStatus::Success,
            $workIds[1] => ProjectFullTextItemStatus::Failed,
        ]);

        $batch = app(StartProjectFullTextScreeningBatch::class)->handle(
            $project,
            $owner,
            [$reviewer->id],
            1,
        );

        $this->assertSame(1, $batch->assignments()->count());
        $assignment = $batch->assignments()->firstOrFail();

        $this->actingAs($reviewer)
            ->from(route('projects.full-text-screening.queue', $project))
            ->post(route('projects.full-text-screening.assignments.decision', [$project, $assignment]), [
                'decision' => ScreeningDecision::EXCLUDE->value,
                'artifact_inspected' => '1',
                'reason' => 'The full text does not meet the protocol.',
            ])
            ->assertRedirect(route('projects.full-text-screening.queue', $project))
            ->assertSessionHasErrors('exclusion_basis');

        $this->actingAs($reviewer)
            ->post(route('projects.full-text-screening.assignments.decision', [$project, $assignment]), [
                'decision' => ScreeningDecision::EXCLUDE->value,
                'artifact_inspected' => '1',
                'reason' => 'The full text is not an eligible study design.',
                'exclusion_basis' => 'Wrong study design',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('screening_decisions', [
            'project_id' => $project->id,
            'stage' => ScreeningStage::FULL_TEXT->value,
            'decision' => ScreeningDecision::EXCLUDE->value,
        ]);
    }

    public function test_full_text_screening_conflicts_are_stage_scoped_and_resolvable(): void
    {
        [$project, $owner, $reviewer, , $workIds, , $adjudicator] = $this->completedScreeningProject([
            ScreeningDecision::INCLUDE,
        ]);
        $this->completedRetrievalBatch($project, $owner, [
            $workIds[0] => ProjectFullTextItemStatus::Success,
        ]);

        $batch = app(StartProjectFullTextScreeningBatch::class)->handle(
            $project,
            $owner,
            [$reviewer->id, $adjudicator->id],
            2,
        );
        $assignments = $batch->assignments()->orderBy('sort_order')->get();

        app(RecordProjectFullTextScreeningDecision::class)->handle(
            $assignments[0],
            $assignments[0]->assignedTo,
            ScreeningDecision::INCLUDE->value,
            'The intervention and outcomes match the protocol.',
            true,
        );
        app(RecordProjectFullTextScreeningDecision::class)->handle(
            $assignments[1],
            $assignments[1]->assignedTo,
            ScreeningDecision::EXCLUDE->value,
            'The full text has the wrong study design.',
            true,
            exclusionBasis: ['Wrong study design'],
        );

        $conflict = ProjectScreeningConflict::query()
            ->where('batch_id', $batch->id)
            ->where('stage', ScreeningStage::FULL_TEXT->value)
            ->firstOrFail();

        app(ResolveProjectFullTextScreeningConflict::class)->handle(
            $conflict,
            $adjudicator,
            ScreeningDecision::EXCLUDE->value,
            'Adjudicator confirmed the study design exclusion after full-text review.',
            exclusionBasis: ['Wrong study design'],
        );

        $conflict->refresh();
        $this->assertSame('resolved', $conflict->status->value);
        $this->assertDatabaseHas('screening_decisions', [
            'id' => $conflict->resolved_decision_id,
            'stage' => ScreeningStage::FULL_TEXT->value,
            'decision_source' => 'human_adjudication',
        ]);
        $this->assertDatabaseMissing('project_screening_conflicts', [
            'batch_id' => $batch->id,
            'stage' => ScreeningStage::TITLE_ABSTRACT->value,
        ]);
    }

    /**
     * @param  list<ScreeningDecision>  $finalDecisions
     * @param  list<string>|null  $titles
     * @return array{0: Project, 1: User, 2: User, 3: User, 4: list<string>, 5: string, 6: User}
     */
    private function completedScreeningProject(array $finalDecisions, ?array $titles = null): array
    {
        [$workspace, $owner] = $this->sharedWorkspace();
        $project = app(CreateProject::class)->handle($workspace, $owner, 'Full-text workflow test', ReviewType::SystematicReview);
        $reviewer = $this->projectMember($project, ProjectRole::Reviewer);
        $viewer = $this->projectMember($project, ProjectRole::Viewer);
        $adjudicator = $this->projectMember($project, ProjectRole::Adjudicator);
        $this->projectMember($project, ProjectRole::Owner, user: $owner);
        $this->completeProtocol($project, $owner);

        $project->forceFill([
            'status' => ProjectStatus::LockedCorpus,
            'locked_at' => now(),
            'locked_by' => (string) $owner->id,
            'lock_reason' => 'Ready for title and abstract screening.',
        ])->save();

        $workIds = [];
        foreach ($finalDecisions as $index => $decision) {
            $workId = (string) Str::uuid();
            $workIds[] = $workId;
            $this->persistWork(
                $workId,
                $titles[$index] ?? 'Full-text workflow candidate '.($index + 1),
                2026 - $index,
                primaryDoi: '10.1000/fulltext.'.($index + 1),
            );
        }

        $snapshotId = $this->representativeSnapshot($project, $owner, $workIds);
        $batch = app(StartProjectScreeningBatch::class)->handle(
            $project,
            $owner,
            [$reviewer->id, $adjudicator->id],
            2,
            'Completed title and abstract screening',
        );

        $workGroups = ProjectScreeningAssignment::query()
            ->where('batch_id', $batch->id)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('work_id')
            ->values();

        foreach ($finalDecisions as $index => $decision) {
            $this->recordScreeningPair(
                $workGroups->get($index, collect()),
                $decision,
                $decision,
                'Final '.$decision->value.' decision.',
                'Second final '.$decision->value.' decision.',
            );
        }

        $batch->refresh();
        $this->assertSame(ProjectScreeningBatchStatus::Completed, $batch->status);

        return [$project->refresh()->load(['workspace', 'protocol']), $owner, $reviewer, $viewer, $workIds, $snapshotId, $adjudicator];
    }

    /**
     * @param  array<string, ProjectFullTextItemStatus>  $statusesByWorkId
     */
    private function completedRetrievalBatch(Project $project, User $owner, array $statusesByWorkId): ProjectFullTextBatch
    {
        Queue::fake();
        $batch = app(StartProjectFullTextBatch::class)->handle($project, $owner);

        foreach ($batch->items()->get() as $item) {
            $status = $statusesByWorkId[$item->work_id] ?? ProjectFullTextItemStatus::Success;

            $item->forceFill([
                'status' => $status,
                'source_alias' => $status === ProjectFullTextItemStatus::Success ? 'demo_oa' : null,
                'artifact_type' => $status === ProjectFullTextItemStatus::Success ? 'pdf' : null,
                'artifact_path' => $status === ProjectFullTextItemStatus::Success
                    ? 'full-text/projects/'.$project->id.'/batches/'.$batch->id.'/'.$item->work_id.'.pdf'
                    : null,
                'error_message' => $status === ProjectFullTextItemStatus::Failed
                    ? 'Demo retrieval failure.'
                    : null,
                'completed_at' => now(),
            ])->save();
        }

        return app(RefreshProjectFullTextBatchCounts::class)->handle($batch->refresh(), completeIfTerminal: true);
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

    private function projectMember(
        Project $project,
        ProjectRole $role,
        ProjectMembershipStatus $status = ProjectMembershipStatus::Active,
        ?User $user = null,
    ): User {
        $user ??= $this->workspaceMemberUser($project->workspace, WorkspaceRole::Member);
        $project->memberships()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'role' => $role,
                'status' => $status,
                'joined_at' => $status === ProjectMembershipStatus::Active ? now() : null,
                'removed_at' => $status === ProjectMembershipStatus::Removed ? now() : null,
            ],
        );

        return $user->refresh();
    }

    private function workspaceMemberUser(Workspace $workspace, WorkspaceRole $role): User
    {
        $user = User::factory()->create();
        app(CreatePersonalWorkspace::class)->handle($user);
        $this->workspaceMember($workspace, $user, $role);
        $user->forceFill(['current_workspace_id' => $workspace->id])->save();

        return $user;
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

    private function completeProtocol(Project $project, User $owner): void
    {
        ProjectProtocol::query()->updateOrCreate(
            ['project_id' => $project->id],
            [
                'status' => ProtocolStatus::Complete,
                'version' => 1,
                'title' => $project->name,
                'research_question' => 'What evidence supports the full-text workflow?',
                'background' => 'The team needs a stable retrieval handoff.',
                'inclusion_criteria' => 'Eligible intervention studies.',
                'exclusion_criteria' => 'Editorials and unrelated populations.',
                'target_providers' => ['openalex', 'crossref'],
                'no_date_limit' => true,
                'language_policy' => 'English-language records.',
                'min_reviewer_count' => 2,
                'ai_screening_policy' => 'human_only',
                'full_text_policy' => 'optional',
                'created_by' => $owner->id,
                'updated_by' => $owner->id,
                'completed_at' => now(),
            ],
        );
    }

    private function persistWork(string $id, string $title, int $year, string $primaryDoi): void
    {
        DB::table('scholarly_works')->insert([
            'id' => $id,
            'title' => $title,
            'abstract' => 'A structured abstract for full-text workflow testing.',
            'year' => $year,
            'venue_name' => 'Journal of Demo Evidence',
            'venue_type' => 'journal',
            'url' => 'https://example.test/'.$id,
            'language' => 'en',
            'cited_by_count' => 12,
            'is_retracted' => false,
            'retrieved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('work_external_ids')->insert([
            'id' => (string) Str::uuid(),
            'work_id' => $id,
            'namespace' => 'doi',
            'value' => $primaryDoi,
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('work_providers')->insert([
            'id' => (string) Str::uuid(),
            'work_id' => $id,
            'provider_alias' => 'openalex',
            'provider_work_id' => 'W-'.$id,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  list<string>  $workIds
     */
    private function representativeSnapshot(Project $project, User $owner, array $workIds): string
    {
        $snapshotId = (string) Str::uuid();
        DB::table('corpus_snapshots')->insert([
            'id' => $snapshotId,
            'project_id' => $project->id,
            'locked_at' => now(),
            'work_count' => count($workIds),
            'created_by' => (string) $owner->id,
            'lock_reason' => 'Ready for full-text workflow test.',
            'metadata' => json_encode([
                'source' => 'test',
                'representative_snapshot' => true,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($workIds as $index => $workId) {
            $includedAt = now()->addSeconds($index);

            DB::table('corpus_snapshot_works')->insert([
                'id' => (string) Str::uuid(),
                'snapshot_id' => $snapshotId,
                'work_id' => $workId,
                'search_query_ids' => json_encode([(string) Str::uuid()]),
                'provider_aliases' => json_encode(['openalex']),
                'provenance' => json_encode([
                    [
                        'provider_alias' => 'openalex',
                        'provider_work_id' => 'W-'.$workId,
                        'rank' => 1,
                        'seen_at' => now()->toISOString(),
                    ],
                ]),
                'included_at' => $includedAt,
                'created_at' => $includedAt,
                'updated_at' => $includedAt,
            ]);
        }

        return $snapshotId;
    }

    private function recordScreeningPair(
        mixed $assignments,
        ScreeningDecision $firstDecision,
        ScreeningDecision $secondDecision,
        string $firstReason,
        string $secondReason,
    ): void {
        $first = $assignments->first();
        $second = $assignments->skip(1)->first();

        if (! $first instanceof ProjectScreeningAssignment || ! $second instanceof ProjectScreeningAssignment) {
            return;
        }

        app(RecordProjectScreeningDecision::class)->handle(
            $first,
            $first->assignedTo,
            $firstDecision->value,
            $firstReason,
        );

        app(RecordProjectScreeningDecision::class)->handle(
            $second,
            $second->assignedTo,
            $secondDecision->value,
            $secondReason,
        );
    }

    private function fakeFullTextCore(string $skippedWorkId): void
    {
        $this->app->bind(FullTextSourceCollection::class, fn (): FullTextSourceCollection => new FullTextSourceCollection(
            new class implements FullTextCandidateSourcePort
            {
                public function resolve(ScholarlyWork $work): ?string
                {
                    return $this->resolveCandidate($work)?->url;
                }

                public function resolveCandidate(ScholarlyWork $work): ?FullTextSourceCandidate
                {
                    $path = str_contains($work->title(), 'Bad PDF') ? 'bad.pdf' : 'success.pdf';

                    return FullTextSourceCandidate::pdf('https://example.test/'.$path, [
                        'license' => 'demo-open-access',
                    ]);
                }

                public function alias(): string
                {
                    return 'demo_oa';
                }

                public function supports(ScholarlyWork $work): bool
                {
                    return true;
                }
            },
        ));
        $this->app->bind(PdfDownloaderPort::class, fn (): PdfDownloaderPort => new class implements PdfDownloaderPort
        {
            public function download(string $url): DownloadResult
            {
                if (str_contains($url, 'bad.pdf')) {
                    return new DownloadResult('not a pdf', 200, 'application/pdf');
                }

                return new DownloadResult("%PDF-1.4\n1 0 obj\n<<>>\nendobj\n", 200, 'application/pdf');
            }
        });
        $this->app->bind(WorkRepositoryPort::class, fn (): WorkRepositoryPort => new class($skippedWorkId) implements WorkRepositoryPort
        {
            public function __construct(private readonly string $skippedWorkId) {}

            public function findById(WorkId $id): ?ScholarlyWork
            {
                if ($id->value === $this->skippedWorkId) {
                    return null;
                }

                return (new EloquentWorkRepository)->findById($id);
            }

            public function findManyByIds(array $ids): array
            {
                return (new EloquentWorkRepository)->findManyByIds($ids);
            }

            public function save(ScholarlyWork $work): void
            {
                (new EloquentWorkRepository)->save($work);
            }
        });

        $this->app->forgetInstance(FullTextSourceCollection::class);
        $this->app->forgetInstance(PdfDownloaderPort::class);
        $this->app->forgetInstance(WorkRepositoryPort::class);
        $this->app->forgetInstance(RetrieveFullTextHandler::class);
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
