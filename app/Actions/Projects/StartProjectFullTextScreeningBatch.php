<?php

namespace App\Actions\Projects;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\ProjectRole;
use App\Enums\ProjectScreeningAssignmentStatus;
use App\Enums\ProjectScreeningBatchStatus;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectFullTextBatch;
use App\Models\ProjectScreeningAssignment;
use App\Models\ProjectScreeningBatch;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Nexus\Screening\Application\Port\ScreeningRunRepositoryPort;
use Nexus\Screening\Domain\ScreeningRun;
use Nexus\Screening\Domain\ScreeningRunMode;
use Nexus\Screening\Domain\ScreeningStage;

class StartProjectFullTextScreeningBatch
{
    public function __construct(
        private readonly BuildProjectFullTextScreeningCandidates $candidateBuilder,
        private readonly BuildProjectScreeningCriteria $criteria,
        private readonly ScreeningRunRepositoryPort $screeningRuns,
        private readonly RefreshProjectScreeningBatchCounts $refreshCounts,
        private readonly RecordAuditEvent $audit,
    ) {}

    /**
     * @param  list<int|string>  $reviewerIds
     */
    public function handle(
        Project $project,
        User $actor,
        array $reviewerIds,
        ?int $requiredReviewerCount = null,
        ?string $name = null,
    ): ProjectScreeningBatch {
        $project->loadMissing(['workspace', 'protocol']);

        if (! $actor->can('manageFullTextScreening', $project)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($actor, $name, $project, $requiredReviewerCount, $reviewerIds): ProjectScreeningBatch {
            $candidateSet = $this->candidateBuilder->handle($project);

            if (! $candidateSet['ready']) {
                throw ValidationException::withMessages([
                    'full_text_screening' => $candidateSet['blockers'][0] ?? __('Full-text screening is not ready for this project.'),
                ]);
            }

            $fullTextBatch = $candidateSet['full_text_batch'];
            if (! $fullTextBatch instanceof ProjectFullTextBatch) {
                throw ValidationException::withMessages([
                    'full_text_screening' => __('Complete full-text retrieval before full-text screening.'),
                ]);
            }

            $required = $requiredReviewerCount ?? max(1, (int) ($project->protocol?->min_reviewer_count ?? 2));
            if ($required < 1) {
                throw ValidationException::withMessages([
                    'required_reviewer_count' => __('Full-text screening requires at least one reviewer per work.'),
                ]);
            }

            $reviewers = $this->eligibleReviewers($project, $reviewerIds);
            if ($reviewers->count() < $required) {
                throw ValidationException::withMessages([
                    'reviewers' => __('Select at least :count active project reviewers or adjudicators.', [
                        'count' => $required,
                    ]),
                ]);
            }

            $this->assertNoExistingBatch($project);

            $screenable = $candidateSet['candidates'];
            $criteria = $this->criteria->handle($project, ScreeningStage::FULL_TEXT);
            $runId = (string) Str::uuid();
            $this->screeningRuns->start(ScreeningRun::start(
                id: $runId,
                projectId: (string) $project->id,
                stage: ScreeningStage::FULL_TEXT,
                mode: ScreeningRunMode::HUMAN,
                criteria: $criteria,
                name: $name ?? 'Full-text screening',
                config: [
                    'mode' => ScreeningRunMode::HUMAN->value,
                    'required_reviewer_count' => $required,
                    'artifact_policy' => 'successful_retrieval_required',
                ],
                source: [
                    'type' => 'full_text_retrieval_batch',
                    'full_text_batch_id' => (string) $fullTextBatch->id,
                    'title_abstract_batch_id' => (string) $candidateSet['title_abstract_batch']->id,
                    'snapshot_id' => (string) $candidateSet['snapshot']->id,
                    'screenable_count' => count($screenable),
                    'follow_up_count' => $candidateSet['follow_up']['total'],
                ],
            ));

            $batch = ProjectScreeningBatch::create([
                'project_id' => $project->id,
                'screening_run_id' => $runId,
                'stage' => ScreeningStage::FULL_TEXT->value,
                'status' => ProjectScreeningBatchStatus::Active,
                'required_reviewer_count' => $required,
                'criteria_hash' => $criteria->hash(),
                'snapshot_id' => (string) $candidateSet['snapshot']->id,
                'source_full_text_batch_id' => $fullTextBatch->id,
                'assignment_policy' => [
                    'strategy' => 'round_robin',
                    'reviewer_ids' => $reviewers->pluck('id')->values()->all(),
                    'artifact_policy' => 'successful_retrieval_required',
                ],
                'counts' => [],
                'created_by' => $actor->id,
                'started_at' => now(),
            ]);

            $this->createAssignments($batch, $actor, $screenable, $reviewers, $required);

            $project->forceFill(['status' => ProjectStatus::Screening])->save();

            $this->audit->handle(
                'project.full_text_screening.batch_started',
                $batch,
                $actor,
                $project->workspace,
                metadata: [
                    'batch_id' => $batch->id,
                    'screening_run_id' => $runId,
                    'full_text_batch_id' => $fullTextBatch->id,
                    'snapshot_id' => (string) $candidateSet['snapshot']->id,
                    'screenable_count' => count($screenable),
                    'assignment_count' => count($screenable) * $required,
                    'follow_up_count' => $candidateSet['follow_up']['total'],
                ],
                project: $project,
            );

            return $this->refreshCounts->handle($batch);
        });
    }

    /**
     * @param  list<int|string>  $reviewerIds
     * @return Collection<int, User>
     */
    private function eligibleReviewers(Project $project, array $reviewerIds): Collection
    {
        $ids = collect($reviewerIds)
            ->map(fn (int|string $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $memberships = $project->activeMemberships()
            ->with('user')
            ->whereIn('user_id', $ids->all())
            ->whereIn('role', [ProjectRole::Reviewer->value, ProjectRole::Adjudicator->value])
            ->get()
            ->keyBy('user_id');

        return $ids
            ->map(fn (int $id): ?User => $memberships->get($id)?->user)
            ->filter(fn (?User $user): bool => $user instanceof User
                && ! $user->isDisabled()
                && $user->workspaceRole($project->workspace) !== null)
            ->values();
    }

    private function assertNoExistingBatch(Project $project): void
    {
        $exists = ProjectScreeningBatch::query()
            ->where('project_id', $project->id)
            ->where('stage', ScreeningStage::FULL_TEXT->value)
            ->whereIn('status', [
                ProjectScreeningBatchStatus::Draft->value,
                ProjectScreeningBatchStatus::Active->value,
                ProjectScreeningBatchStatus::Conflicts->value,
                ProjectScreeningBatchStatus::Completed->value,
            ])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'full_text_screening' => __('This project already has a full-text screening batch.'),
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @param  Collection<int, User>  $reviewers
     */
    private function createAssignments(
        ProjectScreeningBatch $batch,
        User $actor,
        array $candidates,
        Collection $reviewers,
        int $requiredReviewerCount,
    ): void {
        $now = now();
        $rows = [];
        $reviewerCount = $reviewers->count();
        $sortOrder = 0;

        foreach ($candidates as $workIndex => $candidate) {
            for ($slot = 0; $slot < $requiredReviewerCount; $slot++) {
                $reviewer = $reviewers[($workIndex + $slot) % $reviewerCount];

                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'project_id' => $batch->project_id,
                    'batch_id' => $batch->id,
                    'work_id' => $candidate['work_id'],
                    'assigned_to' => $reviewer->id,
                    'assigned_by' => $actor->id,
                    'stage' => $batch->stage,
                    'status' => ProjectScreeningAssignmentStatus::Pending->value,
                    'screening_decision_id' => null,
                    'source_full_text_item_id' => $candidate['full_text_item']['id'],
                    'sort_order' => ++$sortOrder,
                    'assigned_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        ProjectScreeningAssignment::insert($rows);
    }
}
