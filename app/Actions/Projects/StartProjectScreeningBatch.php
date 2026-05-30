<?php

namespace App\Actions\Projects;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\ProjectRole;
use App\Enums\ProjectScreeningAssignmentStatus;
use App\Enums\ProjectScreeningBatchStatus;
use App\Enums\ProjectStatus;
use App\Models\Project;
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

class StartProjectScreeningBatch
{
    public function __construct(
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

        if (! $actor->can('manageScreening', $project)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($actor, $name, $project, $requiredReviewerCount, $reviewerIds): ProjectScreeningBatch {
            $snapshot = $this->latestReadySnapshot($project);
            $workIds = $this->snapshotWorkIds((string) $snapshot->id);

            if ($workIds === []) {
                throw ValidationException::withMessages([
                    'screening' => __('The locked corpus snapshot has no records to screen.'),
                ]);
            }

            $required = $requiredReviewerCount ?? max(1, (int) ($project->protocol?->min_reviewer_count ?? 2));
            if ($required < 1) {
                throw ValidationException::withMessages([
                    'required_reviewer_count' => __('Screening requires at least one reviewer per work.'),
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

            $this->assertNoExistingScreeningBatch($project);

            $criteria = $this->criteria->handle($project);
            $runId = (string) Str::uuid();
            $this->screeningRuns->start(ScreeningRun::start(
                id: $runId,
                projectId: (string) $project->id,
                stage: ScreeningStage::TITLE_ABSTRACT,
                mode: ScreeningRunMode::HUMAN,
                criteria: $criteria,
                name: $name ?? 'Title and abstract screening',
                config: [
                    'mode' => ScreeningRunMode::HUMAN->value,
                    'required_reviewer_count' => $required,
                ],
                source: [
                    'type' => 'locked_corpus_snapshot',
                    'snapshot_id' => (string) $snapshot->id,
                    'work_count' => count($workIds),
                ],
            ));

            $batch = ProjectScreeningBatch::create([
                'project_id' => $project->id,
                'screening_run_id' => $runId,
                'stage' => ScreeningStage::TITLE_ABSTRACT->value,
                'status' => ProjectScreeningBatchStatus::Active,
                'required_reviewer_count' => $required,
                'criteria_hash' => $criteria->hash(),
                'snapshot_id' => (string) $snapshot->id,
                'assignment_policy' => [
                    'strategy' => 'round_robin',
                    'reviewer_ids' => $reviewers->pluck('id')->values()->all(),
                ],
                'counts' => [],
                'created_by' => $actor->id,
                'started_at' => now(),
            ]);

            $this->createAssignments($batch, $actor, $workIds, $reviewers, $required);

            $project->forceFill(['status' => ProjectStatus::Screening])->save();

            $this->audit->handle(
                'project.screening.batch_started',
                $batch,
                $actor,
                $project->workspace,
                metadata: [
                    'batch_id' => $batch->id,
                    'screening_run_id' => $runId,
                    'snapshot_id' => (string) $snapshot->id,
                    'work_count' => count($workIds),
                    'assignment_count' => count($workIds) * $required,
                ],
                project: $project,
            );

            return $this->refreshCounts->handle($batch);
        });
    }

    private function latestReadySnapshot(Project $project): object
    {
        if (! $project->isLocked()) {
            throw ValidationException::withMessages([
                'screening' => __('Lock the corpus before starting screening.'),
            ]);
        }

        $snapshot = DB::table('corpus_snapshots')
            ->where('project_id', $project->id)
            ->orderByDesc('locked_at')
            ->orderByDesc('created_at')
            ->first();

        if (! $snapshot) {
            throw ValidationException::withMessages([
                'screening' => __('The locked project does not have a corpus snapshot.'),
            ]);
        }

        $metadata = $this->decodeObject($snapshot->metadata);
        if (($metadata['representative_snapshot'] ?? false) !== true) {
            throw ValidationException::withMessages([
                'screening' => __('Screening requires a representative-aware locked corpus snapshot.'),
            ]);
        }

        return $snapshot;
    }

    /**
     * @return list<string>
     */
    private function snapshotWorkIds(string $snapshotId): array
    {
        return DB::table('corpus_snapshot_works')
            ->where('snapshot_id', $snapshotId)
            ->orderBy('included_at')
            ->orderBy('work_id')
            ->pluck('work_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();
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

    private function assertNoExistingScreeningBatch(Project $project): void
    {
        $exists = ProjectScreeningBatch::query()
            ->where('project_id', $project->id)
            ->where('stage', ScreeningStage::TITLE_ABSTRACT->value)
            ->whereIn('status', [
                ProjectScreeningBatchStatus::Draft->value,
                ProjectScreeningBatchStatus::Active->value,
                ProjectScreeningBatchStatus::Conflicts->value,
                ProjectScreeningBatchStatus::Completed->value,
            ])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'screening' => __('This project already has a title and abstract screening batch.'),
            ]);
        }
    }

    /**
     * @param  list<string>  $workIds
     * @param  Collection<int, User>  $reviewers
     */
    private function createAssignments(
        ProjectScreeningBatch $batch,
        User $actor,
        array $workIds,
        Collection $reviewers,
        int $requiredReviewerCount,
    ): void {
        $now = now();
        $rows = [];
        $reviewerCount = $reviewers->count();
        $sortOrder = 0;

        foreach ($workIds as $workIndex => $workId) {
            for ($slot = 0; $slot < $requiredReviewerCount; $slot++) {
                $reviewer = $reviewers[($workIndex + $slot) % $reviewerCount];

                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'project_id' => $batch->project_id,
                    'batch_id' => $batch->id,
                    'work_id' => $workId,
                    'assigned_to' => $reviewer->id,
                    'assigned_by' => $actor->id,
                    'stage' => $batch->stage,
                    'status' => ProjectScreeningAssignmentStatus::Pending->value,
                    'sort_order' => ++$sortOrder,
                    'assigned_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        ProjectScreeningAssignment::insert($rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeObject(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
