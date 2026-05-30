<?php

namespace App\Actions\Projects;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\ProjectScreeningAssignmentStatus;
use App\Enums\ProjectScreeningBatchStatus;
use App\Enums\ProjectScreeningConflictStatus;
use App\Models\ProjectScreeningAssignment;
use App\Models\ProjectScreeningBatch;
use App\Models\ProjectScreeningConflict;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Nexus\Screening\Application\Port\ScreeningDecisionRepositoryPort;
use Nexus\Screening\Domain\ScreeningDecision;
use Nexus\Screening\Domain\ScreeningRationale;
use Nexus\Screening\Domain\ScreeningStage;
use Nexus\Screening\Domain\ScreeningVerdict;

class RecordProjectScreeningDecision
{
    public function __construct(
        private readonly ScreeningDecisionRepositoryPort $decisions,
        private readonly RefreshProjectScreeningBatchCounts $refreshCounts,
        private readonly RecordAuditEvent $audit,
    ) {}

    /**
     * @param  list<string>  $evidence
     * @param  list<string>  $uncertainty
     * @param  list<string>  $exclusionBasis
     */
    public function handle(
        ProjectScreeningAssignment $assignment,
        User $actor,
        string $decision,
        string $reason,
        array $evidence = [],
        array $uncertainty = [],
        array $exclusionBasis = [],
    ): ProjectScreeningAssignment {
        $assignment->loadMissing(['batch.project.workspace']);
        $batch = $assignment->batch;
        $project = $batch->project;

        if (! $actor->can('screenAssignedWork', $project) || (int) $assignment->assigned_to !== (int) $actor->id) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($actor, $assignment, $batch, $decision, $evidence, $exclusionBasis, $project, $reason, $uncertainty): ProjectScreeningAssignment {
            $assignment->refresh();
            $screeningDecision = $this->decision($decision);
            $reason = trim($reason);

            if ($reason === '') {
                throw ValidationException::withMessages([
                    'reason' => __('Record a rationale before submitting this screening decision.'),
                ]);
            }

            if (in_array($assignment->status, [
                ProjectScreeningAssignmentStatus::Conflict,
                ProjectScreeningAssignmentStatus::Resolved,
            ], true)) {
                throw ValidationException::withMessages([
                    'assignment' => __('This assignment is no longer open for reviewer decisions.'),
                ]);
            }

            if (in_array($batch->status, [
                ProjectScreeningBatchStatus::Cancelled,
                ProjectScreeningBatchStatus::Completed,
            ], true)) {
                throw ValidationException::withMessages([
                    'assignment' => __('This screening batch is not accepting new reviewer decisions.'),
                ]);
            }

            $this->assertWorkBelongsToBatchSnapshot($batch, $assignment->work_id);

            $verdict = new ScreeningVerdict(
                id: (string) Str::uuid(),
                screeningRunId: $batch->screening_run_id,
                projectId: (string) $project->id,
                workId: (string) $assignment->work_id,
                stage: ScreeningStage::TITLE_ABSTRACT,
                decision: $screeningDecision,
                confidence: 1.0,
                source: 'human',
                rationale: new ScreeningRationale(
                    reason: $reason,
                    evidence: $this->normalizeList($evidence),
                    uncertainty: $this->normalizeList($uncertainty),
                    exclusionBasis: $this->normalizeList($exclusionBasis),
                ),
                decidedBy: (string) $actor->id,
                decidedAt: new \DateTimeImmutable,
                criteriaHash: $batch->criteria_hash,
                metadata: [
                    'batch_id' => $batch->id,
                    'assignment_id' => $assignment->id,
                ],
            );

            $this->decisions->record($verdict);

            $assignment->forceFill([
                'status' => ProjectScreeningAssignmentStatus::Decided,
                'screening_decision_id' => $verdict->id,
                'decided_at' => now(),
            ])->save();

            $this->reconcileWork($assignment, $actor);

            $this->audit->handle(
                'project.screening.decision_recorded',
                $assignment,
                $actor,
                $project->workspace,
                metadata: [
                    'batch_id' => $batch->id,
                    'work_id' => $assignment->work_id,
                    'decision' => $screeningDecision->value,
                    'screening_decision_id' => $verdict->id,
                ],
                project: $project,
            );

            $this->refreshCounts->handle($batch);

            return $assignment->refresh();
        });
    }

    private function decision(string $decision): ScreeningDecision
    {
        $screeningDecision = ScreeningDecision::tryFrom($decision);

        if (! $screeningDecision instanceof ScreeningDecision) {
            throw ValidationException::withMessages([
                'decision' => __('Choose include, maybe, or exclude.'),
            ]);
        }

        return $screeningDecision;
    }

    private function reconcileWork(ProjectScreeningAssignment $assignment, User $actor): void
    {
        $batch = $assignment->batch;
        $decisionIds = ProjectScreeningAssignment::query()
            ->where('batch_id', $batch->id)
            ->where('work_id', $assignment->work_id)
            ->whereNotNull('screening_decision_id')
            ->pluck('screening_decision_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->values()
            ->all();

        if (count($decisionIds) < $batch->required_reviewer_count) {
            return;
        }

        $decisions = DB::table('screening_decisions')
            ->whereIn('id', $decisionIds)
            ->orderBy('decided_at')
            ->get(['id', 'decision']);

        $uniqueDecisions = $decisions->pluck('decision')->unique()->values();
        $now = now();

        if ($uniqueDecisions->count() > 1) {
            $conflict = ProjectScreeningConflict::query()->firstOrNew([
                'batch_id' => $batch->id,
                'work_id' => $assignment->work_id,
                'stage' => $batch->stage,
            ]);
            $isNew = ! $conflict->exists;

            $conflict->fill([
                'project_id' => $batch->project_id,
                'status' => ProjectScreeningConflictStatus::Open,
                'decision_ids' => $decisions->pluck('id')->values()->all(),
                'opened_at' => $conflict->opened_at ?? $now,
            ])->save();

            ProjectScreeningAssignment::query()
                ->where('batch_id', $batch->id)
                ->where('work_id', $assignment->work_id)
                ->whereIn('screening_decision_id', $decisionIds)
                ->update([
                    'status' => ProjectScreeningAssignmentStatus::Conflict->value,
                    'updated_at' => $now,
                ]);

            if ($isNew) {
                $this->audit->handle(
                    'project.screening.conflict_created',
                    $conflict,
                    $actor,
                    $batch->project->workspace,
                    metadata: [
                        'batch_id' => $batch->id,
                        'work_id' => $assignment->work_id,
                        'decision_ids' => $conflict->decision_ids,
                    ],
                    project: $batch->project,
                );
            }

            return;
        }

        ProjectScreeningAssignment::query()
            ->where('batch_id', $batch->id)
            ->where('work_id', $assignment->work_id)
            ->whereIn('screening_decision_id', $decisionIds)
            ->update([
                'status' => ProjectScreeningAssignmentStatus::Resolved->value,
                'updated_at' => $now,
            ]);
    }

    private function assertWorkBelongsToBatchSnapshot(ProjectScreeningBatch $batch, string $workId): void
    {
        $belongs = $batch->snapshot_id && DB::table('corpus_snapshot_works')
            ->where('snapshot_id', $batch->snapshot_id)
            ->where('work_id', $workId)
            ->exists();

        if (! $belongs) {
            throw ValidationException::withMessages([
                'assignment' => __('This work is not part of the latest locked corpus snapshot.'),
            ]);
        }
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function normalizeList(array $values): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (string $value): string => trim($value),
            $values,
        ), static fn (string $value): bool => $value !== '')));
    }
}
