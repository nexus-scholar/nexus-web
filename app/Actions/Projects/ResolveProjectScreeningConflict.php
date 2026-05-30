<?php

namespace App\Actions\Projects;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\ProjectScreeningAssignmentStatus;
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

class ResolveProjectScreeningConflict
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
        ProjectScreeningConflict $conflict,
        User $actor,
        string $decision,
        string $reason,
        array $evidence = [],
        array $uncertainty = [],
        array $exclusionBasis = [],
    ): ProjectScreeningConflict {
        $conflict->loadMissing(['batch.project.workspace']);
        $batch = $conflict->batch;
        $project = $batch->project;

        if (! $actor->can('resolveScreeningConflict', $project)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($actor, $batch, $conflict, $decision, $evidence, $exclusionBasis, $project, $reason, $uncertainty): ProjectScreeningConflict {
            $conflict->refresh();
            $screeningDecision = $this->decision($decision);
            $reason = trim($reason);

            if ($reason === '') {
                throw ValidationException::withMessages([
                    'reason' => __('Record an audit reason before resolving this conflict.'),
                ]);
            }

            if ($conflict->status !== ProjectScreeningConflictStatus::Open) {
                throw ValidationException::withMessages([
                    'conflict' => __('This conflict is already resolved.'),
                ]);
            }

            $this->assertWorkBelongsToBatchSnapshot($batch, $conflict->work_id);

            $sourceDecisionIds = array_values($conflict->decision_ids ?? []);
            $verdict = new ScreeningVerdict(
                id: (string) Str::uuid(),
                screeningRunId: $batch->screening_run_id,
                projectId: (string) $project->id,
                workId: (string) $conflict->work_id,
                stage: ScreeningStage::TITLE_ABSTRACT,
                decision: $screeningDecision,
                confidence: 1.0,
                source: 'human_adjudication',
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
                    'conflict_id' => $conflict->id,
                    'source_decision_ids' => $sourceDecisionIds,
                ],
            );

            $this->decisions->record($verdict);

            $conflict->forceFill([
                'status' => ProjectScreeningConflictStatus::Resolved,
                'resolved_decision_id' => $verdict->id,
                'resolved_by' => $actor->id,
                'resolution_reason' => $reason,
                'resolved_at' => now(),
            ])->save();

            ProjectScreeningAssignment::query()
                ->where('batch_id', $batch->id)
                ->where('work_id', $conflict->work_id)
                ->update([
                    'status' => ProjectScreeningAssignmentStatus::Resolved->value,
                    'updated_at' => now(),
                ]);

            $this->audit->handle(
                'project.screening.conflict_resolved',
                $conflict,
                $actor,
                $project->workspace,
                $reason,
                [
                    'batch_id' => $batch->id,
                    'work_id' => $conflict->work_id,
                    'source_decision_ids' => $sourceDecisionIds,
                    'resolved_decision_id' => $verdict->id,
                    'decision' => $screeningDecision->value,
                ],
                $project,
            );

            $this->refreshCounts->handle($batch);

            return $conflict->refresh();
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

    private function assertWorkBelongsToBatchSnapshot(ProjectScreeningBatch $batch, string $workId): void
    {
        $belongs = $batch->snapshot_id && DB::table('corpus_snapshot_works')
            ->where('snapshot_id', $batch->snapshot_id)
            ->where('work_id', $workId)
            ->exists();

        if (! $belongs) {
            throw ValidationException::withMessages([
                'conflict' => __('This work is not part of the latest locked corpus snapshot.'),
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
