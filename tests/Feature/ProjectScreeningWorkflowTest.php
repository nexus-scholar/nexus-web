<?php

namespace Tests\Feature;

use App\Actions\Projects\CreateProject;
use App\Actions\Projects\RecordProjectScreeningDecision;
use App\Actions\Projects\ResolveProjectScreeningConflict;
use App\Actions\Projects\StartProjectScreeningBatch;
use App\Actions\Workspaces\CreatePersonalWorkspace;
use App\Actions\Workspaces\CreateSharedWorkspace;
use App\Enums\ProjectMembershipStatus;
use App\Enums\ProjectRole;
use App\Enums\ProjectScreeningAssignmentStatus;
use App\Enums\ProjectScreeningBatchStatus;
use App\Enums\ProjectScreeningConflictStatus;
use App\Enums\ProjectStatus;
use App\Enums\ProtocolStatus;
use App\Enums\ReviewType;
use App\Enums\WorkspaceMembershipStatus;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\ProjectProtocol;
use App\Models\ProjectScreeningAssignment;
use App\Models\ProjectScreeningConflict;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Nexus\Screening\Domain\ScreeningDecision;
use Tests\TestCase;

class ProjectScreeningWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_start_screening_batch_from_locked_snapshot(): void
    {
        [$project, $owner, $workIds, $snapshotId] = $this->lockedScreeningProject(workCount: 2);
        $reviewer = $this->projectMember($project, ProjectRole::Reviewer);
        $adjudicator = $this->projectMember($project, ProjectRole::Adjudicator);

        $batch = app(StartProjectScreeningBatch::class)->handle(
            $project,
            $owner,
            [$reviewer->id, $adjudicator->id],
            2,
            'TA screening v1',
        );

        $this->assertSame(ProjectScreeningBatchStatus::Active, $batch->status);
        $this->assertSame(2, $batch->required_reviewer_count);
        $this->assertSame($snapshotId, $batch->snapshot_id);
        $this->assertSame(4, $batch->counts['assignments']['total']);
        $this->assertSame(4, $batch->counts['assignments']['pending']);

        $this->assertDatabaseHas('screening_runs', [
            'id' => $batch->screening_run_id,
            'project_id' => $project->id,
            'stage' => 'title_abstract',
            'mode' => 'human',
            'status' => 'running',
            'name' => 'TA screening v1',
        ]);
        $this->assertDatabaseCount('project_screening_assignments', 4);

        foreach ($workIds as $workId) {
            $this->assertSame(
                2,
                ProjectScreeningAssignment::query()
                    ->where('batch_id', $batch->id)
                    ->where('work_id', $workId)
                    ->count(),
            );
        }

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'status' => ProjectStatus::Screening->value,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'project_id' => $project->id,
            'event_type' => 'project.screening.batch_started',
            'target_id' => $batch->id,
        ]);
    }

    public function test_missing_or_non_representative_snapshot_blocks_start(): void
    {
        [$project, $owner] = $this->lockedScreeningProject(workCount: 0, createSnapshot: false);
        $reviewer = $this->projectMember($project, ProjectRole::Reviewer);

        $this->expectException(ValidationException::class);

        app(StartProjectScreeningBatch::class)->handle($project, $owner, [$reviewer->id], 1);
    }

    public function test_non_representative_snapshot_blocks_start(): void
    {
        [$project, $owner] = $this->lockedScreeningProject(representativeSnapshot: false);
        $reviewer = $this->projectMember($project, ProjectRole::Reviewer);

        $this->expectException(ValidationException::class);

        app(StartProjectScreeningBatch::class)->handle($project, $owner, [$reviewer->id], 1);
    }

    public function test_only_active_reviewers_and_adjudicators_receive_assignments(): void
    {
        [$project, $owner] = $this->lockedScreeningProject(workCount: 1);
        $reviewer = $this->projectMember($project, ProjectRole::Reviewer);
        $adjudicator = $this->projectMember($project, ProjectRole::Adjudicator);
        $viewer = $this->projectMember($project, ProjectRole::Viewer);
        $removedReviewer = $this->projectMember($project, ProjectRole::Reviewer, ProjectMembershipStatus::Removed);
        $disabledReviewer = $this->projectMember($project, ProjectRole::Reviewer, disabled: true);

        $batch = app(StartProjectScreeningBatch::class)->handle(
            $project,
            $owner,
            [
                $viewer->id,
                $reviewer->id,
                $removedReviewer->id,
                $disabledReviewer->id,
                $adjudicator->id,
            ],
            2,
        );

        $assignedUserIds = ProjectScreeningAssignment::query()
            ->where('batch_id', $batch->id)
            ->pluck('assigned_to')
            ->map(fn (mixed $id): int => (int) $id)
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            collect([$reviewer->id, $adjudicator->id])->sort()->values()->all(),
            $assignedUserIds,
        );
    }

    public function test_project_screening_policies_match_role_boundary(): void
    {
        [$project, $owner] = $this->lockedScreeningProject();
        $workspaceAdmin = $this->workspaceMemberUser($project->workspace, WorkspaceRole::Admin);
        $reviewer = $this->projectMember($project, ProjectRole::Reviewer);
        $adjudicator = $this->projectMember($project, ProjectRole::Adjudicator);
        $viewer = $this->projectMember($project, ProjectRole::Viewer);

        $this->assertTrue($owner->can('viewScreening', $project));
        $this->assertTrue($owner->can('manageScreening', $project));
        $this->assertTrue($workspaceAdmin->can('manageScreening', $project));
        $this->assertTrue($reviewer->can('screenAssignedWork', $project));
        $this->assertTrue($adjudicator->can('resolveScreeningConflict', $project));
        $this->assertFalse($reviewer->can('manageScreening', $project));
        $this->assertFalse($viewer->can('screenAssignedWork', $project));
        $this->assertFalse($viewer->can('resolveScreeningConflict', $project));

        $project->workspace->forceFill([
            'suspended_at' => now(),
            'suspended_by' => $owner->id,
            'suspended_reason' => 'Suspended for screening test.',
        ])->save();

        $this->assertFalse($owner->can('viewScreening', $project));
        $this->assertFalse($workspaceAdmin->can('manageScreening', $project));
    }

    public function test_reviewer_records_decision_and_persists_core_screening_decision(): void
    {
        [$project, $owner] = $this->lockedScreeningProject(workCount: 1);
        $reviewer = $this->projectMember($project, ProjectRole::Reviewer);
        $batch = app(StartProjectScreeningBatch::class)->handle($project, $owner, [$reviewer->id], 1);
        $assignment = $batch->assignments()->firstOrFail();

        $assignment = app(RecordProjectScreeningDecision::class)->handle(
            $assignment,
            $reviewer,
            ScreeningDecision::INCLUDE->value,
            'Primary care digital intervention with patient outcome.',
            evidence: ['primary care', 'digital intervention'],
        );

        $this->assertSame(ProjectScreeningAssignmentStatus::Resolved, $assignment->status);
        $this->assertNotNull($assignment->screening_decision_id);
        $this->assertDatabaseHas('screening_decisions', [
            'id' => $assignment->screening_decision_id,
            'screening_run_id' => $batch->screening_run_id,
            'project_id' => $project->id,
            'work_id' => $assignment->work_id,
            'stage' => 'title_abstract',
            'decision' => 'include',
            'decision_source' => 'human',
            'reason' => 'Primary care digital intervention with patient outcome.',
            'decided_by' => (string) $reviewer->id,
            'included' => true,
        ]);

        $batch->refresh();
        $this->assertSame(ProjectScreeningBatchStatus::Completed, $batch->status);
        $this->assertSame(1, $batch->counts['decisions']['include']);
        $this->assertDatabaseHas('screening_runs', [
            'id' => $batch->screening_run_id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'project_id' => $project->id,
            'event_type' => 'project.screening.decision_recorded',
            'target_id' => $assignment->id,
        ]);
    }

    public function test_reviewer_decision_uses_the_batch_snapshot_not_a_later_snapshot(): void
    {
        [$project, $owner] = $this->lockedScreeningProject(workCount: 1);
        $reviewer = $this->projectMember($project, ProjectRole::Reviewer);
        $batch = app(StartProjectScreeningBatch::class)->handle($project, $owner, [$reviewer->id], 1);
        $assignment = $batch->assignments()->firstOrFail();

        $replacementWorkId = (string) Str::uuid();
        DB::table('scholarly_works')->insert($this->work(
            $replacementWorkId,
            'Later lock replacement record',
            'A record that does not belong to the active screening batch.',
            2025,
        ));
        $this->representativeSnapshot($project, $owner, [$replacementWorkId], now()->addMinutes(5));

        $assignment = app(RecordProjectScreeningDecision::class)->handle(
            $assignment,
            $reviewer,
            ScreeningDecision::INCLUDE->value,
            'The original locked snapshot still owns this assignment.',
        );

        $this->assertSame(ProjectScreeningAssignmentStatus::Resolved, $assignment->status);
        $this->assertSame($batch->snapshot_id, $assignment->batch->snapshot_id);
    }

    public function test_decision_requires_assignment_owner_and_rationale(): void
    {
        [$project, $owner] = $this->lockedScreeningProject(workCount: 1);
        $reviewer = $this->projectMember($project, ProjectRole::Reviewer);
        $otherReviewer = $this->projectMember($project, ProjectRole::Reviewer);
        $batch = app(StartProjectScreeningBatch::class)->handle($project, $owner, [$reviewer->id], 1);
        $assignment = $batch->assignments()->firstOrFail();

        try {
            app(RecordProjectScreeningDecision::class)->handle(
                $assignment,
                $otherReviewer,
                ScreeningDecision::INCLUDE->value,
                'Trying to record another reviewer decision.',
            );
            $this->fail('Expected authorization exception.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('screening_decisions', 0);
        }

        $this->expectException(ValidationException::class);

        app(RecordProjectScreeningDecision::class)->handle(
            $assignment,
            $reviewer,
            ScreeningDecision::INCLUDE->value,
            '',
        );
    }

    public function test_disagreement_creates_open_conflict(): void
    {
        [$project, $owner] = $this->lockedScreeningProject(workCount: 1);
        $reviewer = $this->projectMember($project, ProjectRole::Reviewer);
        $secondReviewer = $this->projectMember($project, ProjectRole::Reviewer);
        $batch = app(StartProjectScreeningBatch::class)->handle($project, $owner, [$reviewer->id, $secondReviewer->id], 2);

        $assignments = $batch->assignments()->orderBy('assigned_to')->get();

        app(RecordProjectScreeningDecision::class)->handle(
            $assignments->first(),
            $assignments->first()->assignedTo,
            ScreeningDecision::INCLUDE->value,
            'Clearly matches the intervention and setting.',
        );
        app(RecordProjectScreeningDecision::class)->handle(
            $assignments->last(),
            $assignments->last()->assignedTo,
            ScreeningDecision::EXCLUDE->value,
            'No comparative outcome is visible in the abstract.',
        );

        $conflict = ProjectScreeningConflict::query()->firstOrFail();

        $this->assertSame(ProjectScreeningConflictStatus::Open, $conflict->status);
        $this->assertCount(2, $conflict->decision_ids);
        $this->assertSame(
            2,
            ProjectScreeningAssignment::query()
                ->where('batch_id', $batch->id)
                ->where('status', ProjectScreeningAssignmentStatus::Conflict->value)
                ->count(),
        );

        $batch->refresh();
        $this->assertSame(ProjectScreeningBatchStatus::Conflicts, $batch->status);
        $this->assertSame(1, $batch->counts['conflicts']['open']);
        $this->assertDatabaseHas('audit_events', [
            'project_id' => $project->id,
            'event_type' => 'project.screening.conflict_created',
            'target_id' => $conflict->id,
        ]);
    }

    public function test_adjudicator_resolves_conflict_with_audit_reason(): void
    {
        [$project, $owner] = $this->lockedScreeningProject(workCount: 1);
        $reviewer = $this->projectMember($project, ProjectRole::Reviewer);
        $secondReviewer = $this->projectMember($project, ProjectRole::Reviewer);
        $adjudicator = $this->projectMember($project, ProjectRole::Adjudicator);
        $batch = app(StartProjectScreeningBatch::class)->handle($project, $owner, [$reviewer->id, $secondReviewer->id], 2);
        $assignments = $batch->assignments()->orderBy('assigned_to')->get();

        app(RecordProjectScreeningDecision::class)->handle(
            $assignments->first(),
            $assignments->first()->assignedTo,
            ScreeningDecision::INCLUDE->value,
            'Relevant intervention and setting.',
        );
        app(RecordProjectScreeningDecision::class)->handle(
            $assignments->last(),
            $assignments->last()->assignedTo,
            ScreeningDecision::EXCLUDE->value,
            'Outcome eligibility is unclear.',
        );

        $conflict = ProjectScreeningConflict::query()->firstOrFail();
        $sourceDecisionIds = $conflict->decision_ids;
        $replacementWorkId = (string) Str::uuid();
        DB::table('scholarly_works')->insert($this->work(
            $replacementWorkId,
            'Later conflict replacement record',
            'A record from a later locked snapshot.',
            2025,
        ));
        $this->representativeSnapshot($project, $owner, [$replacementWorkId], now()->addMinutes(5));

        $resolved = app(ResolveProjectScreeningConflict::class)->handle(
            $conflict,
            $adjudicator,
            ScreeningDecision::NEEDS_REVIEW->value,
            'Route to full text because abstract eligibility is uncertain.',
            uncertainty: ['study design unclear'],
        );

        $this->assertSame(ProjectScreeningConflictStatus::Resolved, $resolved->status);
        $this->assertNotNull($resolved->resolved_decision_id);
        $this->assertSame((int) $adjudicator->id, (int) $resolved->resolved_by);
        $this->assertDatabaseHas('screening_decisions', [
            'id' => $resolved->resolved_decision_id,
            'decision' => 'needs_review',
            'decision_source' => 'human_adjudication',
            'reason' => 'Route to full text because abstract eligibility is uncertain.',
            'decided_by' => (string) $adjudicator->id,
        ]);

        $metadata = DB::table('screening_decisions')
            ->where('id', $resolved->resolved_decision_id)
            ->value('metadata');

        $this->assertSame($sourceDecisionIds, json_decode((string) $metadata, true)['source_decision_ids']);
        $this->assertSame(
            2,
            ProjectScreeningAssignment::query()
                ->where('batch_id', $batch->id)
                ->where('status', ProjectScreeningAssignmentStatus::Resolved->value)
                ->count(),
        );

        $batch->refresh();
        $this->assertSame(ProjectScreeningBatchStatus::Completed, $batch->status);
        $this->assertSame(1, $batch->counts['conflicts']['resolved']);
        $this->assertDatabaseHas('audit_events', [
            'project_id' => $project->id,
            'event_type' => 'project.screening.conflict_resolved',
            'reason' => 'Route to full text because abstract eligibility is uncertain.',
        ]);
    }

    public function test_reviewer_cannot_resolve_conflict(): void
    {
        [$project, $owner] = $this->lockedScreeningProject(workCount: 1);
        $reviewer = $this->projectMember($project, ProjectRole::Reviewer);
        $secondReviewer = $this->projectMember($project, ProjectRole::Reviewer);
        $batch = app(StartProjectScreeningBatch::class)->handle($project, $owner, [$reviewer->id, $secondReviewer->id], 2);
        $assignments = $batch->assignments()->orderBy('assigned_to')->get();

        app(RecordProjectScreeningDecision::class)->handle(
            $assignments->first(),
            $assignments->first()->assignedTo,
            ScreeningDecision::INCLUDE->value,
            'Relevant intervention and setting.',
        );
        app(RecordProjectScreeningDecision::class)->handle(
            $assignments->last(),
            $assignments->last()->assignedTo,
            ScreeningDecision::EXCLUDE->value,
            'Outcome eligibility is unclear.',
        );

        $this->expectException(AuthorizationException::class);

        app(ResolveProjectScreeningConflict::class)->handle(
            ProjectScreeningConflict::query()->firstOrFail(),
            $reviewer,
            ScreeningDecision::NEEDS_REVIEW->value,
            'Reviewer attempted conflict resolution.',
        );
    }

    /**
     * @return array{0: Project, 1: User, 2: list<string>, 3: string|null}
     */
    private function lockedScreeningProject(
        int $workCount = 2,
        bool $representativeSnapshot = true,
        bool $createSnapshot = true,
    ): array {
        [$workspace, $owner] = $this->sharedWorkspace();
        $project = app(CreateProject::class)->handle($workspace, $owner, 'Screening workflow test', ReviewType::SystematicReview);

        ProjectProtocol::query()->updateOrCreate(
            ['project_id' => $project->id],
            [
                'status' => ProtocolStatus::Complete,
                'version' => 1,
                'title' => 'Screening workflow test',
                'research_question' => 'What evidence supports digital primary care intervention?',
                'background' => 'The team needs a stable screening protocol.',
                'inclusion_criteria' => 'Peer-reviewed primary care intervention studies.',
                'exclusion_criteria' => 'Editorials, protocols, and non-human studies.',
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

        $project->forceFill([
            'status' => ProjectStatus::LockedCorpus,
            'locked_at' => now(),
            'locked_by' => (string) $owner->id,
            'lock_reason' => 'Ready for title and abstract screening.',
        ])->save();

        $workIds = [];
        for ($i = 1; $i <= $workCount; $i++) {
            $workIds[] = (string) Str::uuid();
        }

        if ($workIds !== []) {
            DB::table('scholarly_works')->insert(collect($workIds)
                ->map(fn (string $workId, int $index): array => $this->work(
                    $workId,
                    'Digital primary care intervention '.$index + 1,
                    'A structured abstract for screening work '.$index + 1,
                    2026 - $index,
                ))
                ->all());
        }

        $snapshotId = null;
        if ($createSnapshot) {
            $snapshotId = (string) Str::uuid();
            DB::table('corpus_snapshots')->insert([
                'id' => $snapshotId,
                'project_id' => $project->id,
                'locked_at' => now(),
                'work_count' => count($workIds),
                'created_by' => (string) $owner->id,
                'lock_reason' => 'Ready for title and abstract screening.',
                'metadata' => json_encode([
                    'source' => 'test',
                    'representative_snapshot' => $representativeSnapshot,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($workIds as $workId) {
                $this->snapshotWork($snapshotId, $workId);
            }
        }

        return [$project->refresh()->load(['workspace', 'protocol']), $owner, $workIds, $snapshotId];
    }

    /**
     * @param  list<string>  $workIds
     */
    private function representativeSnapshot(Project $project, User $owner, array $workIds, mixed $lockedAt): string
    {
        $snapshotId = (string) Str::uuid();
        DB::table('corpus_snapshots')->insert([
            'id' => $snapshotId,
            'project_id' => $project->id,
            'locked_at' => $lockedAt,
            'work_count' => count($workIds),
            'created_by' => (string) $owner->id,
            'lock_reason' => 'Later representative test snapshot.',
            'metadata' => json_encode([
                'source' => 'test',
                'representative_snapshot' => true,
            ]),
            'created_at' => $lockedAt,
            'updated_at' => $lockedAt,
        ]);

        foreach ($workIds as $workId) {
            $this->snapshotWork($snapshotId, $workId);
        }

        return $snapshotId;
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

    private function workspaceMemberUser(Workspace $workspace, WorkspaceRole $role): User
    {
        $user = User::factory()->create();
        app(CreatePersonalWorkspace::class)->handle($user);
        $this->workspaceMember($workspace, $user, $role);
        $user->forceFill(['current_workspace_id' => $workspace->id])->save();

        return $user;
    }

    private function projectMember(
        Project $project,
        ProjectRole $role,
        ProjectMembershipStatus $status = ProjectMembershipStatus::Active,
        bool $disabled = false,
    ): User {
        $user = $this->workspaceMemberUser($project->workspace, WorkspaceRole::Member);
        $project->memberships()->create([
            'user_id' => $user->id,
            'role' => $role,
            'status' => $status,
            'joined_at' => $status === ProjectMembershipStatus::Active ? now() : null,
            'removed_at' => $status === ProjectMembershipStatus::Removed ? now() : null,
        ]);

        if ($disabled) {
            $user->forceFill([
                'disabled_at' => now(),
                'disabled_by' => $project->owner_user_id,
                'disabled_reason' => 'Disabled test reviewer.',
            ])->save();
        }

        return $user->refresh();
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

    private function work(string $id, string $title, ?string $abstract, int $year): array
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
            'is_retracted' => false,
            'retrieved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function snapshotWork(string $snapshotId, string $workId): void
    {
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
            'included_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
