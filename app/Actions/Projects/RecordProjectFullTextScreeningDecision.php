<?php

namespace App\Actions\Projects;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\ProjectFullTextItemStatus;
use App\Enums\ProjectScreeningAssignmentStatus;
use App\Enums\ProjectScreeningBatchStatus;
use App\Enums\ProjectScreeningConflictStatus;
use App\Models\ProjectFullTextItem;
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

class RecordProjectFullTextScreeningDecision
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
        bool $artifactInspected,
        array $evidence = [],
        array $uncertainty = [],
        array $exclusionBasis = [],
    ): ProjectScreeningAssignment {
        $assignment->loadMissing(['batch.project.workspace', 'sourceFullTextItem']);
        $batch = $assignment->batch;
        $project = $batch->project;

        if (! $actor->can('screenAssignedFullText', $project) || (int) $assignment->assigned_to !== (int) $actor->id) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($actor, $artifactInspected, $assignment, $batch, $decision, $evidence, $exclusionBasis, $project, $reason, $uncertainty): ProjectScreeningAssignment {
            $assignment->refresh();
            $screeningDecision = $this->decision($decision);
            $reason = trim($reason);
            $exclusionBasis = $this->normalizeList($exclusionBasis);

            if ((string) $batch->stage !== ScreeningStage::FULL_TEXT->value || (string) $assignment->stage !== ScreeningStage::FULL_TEXT->value) {
                throw ValidationException::withMessages([
                    'assignment' => __('This assignment is not part of full-text screening.'),
                ]);
            }

            if ($reason === '') {
                throw ValidationException::withMessages([
                    'reason' => __('Record a rationale before submitting this full-text decision.'),
                ]);
            }

            if (! $artifactInspected) {
                throw ValidationException::withMessages([
                    'artifact_inspected' => __('Confirm that the linked full-text artifact was inspected.'),
                ]);
            }

            if ($screeningDecision === ScreeningDecision::EXCLUDE && $exclusionBasis === []) {
                throw ValidationException::withMessages([
                    'exclusion_basis' => __('Choose or describe at least one exclusion reason.'),
                ]);
            }

            if (in_array($assignment->status, [
                ProjectScreeningAssignmentStatus::Conflict,
                ProjectScreeningAssignmentStatus::Resolved,
            ], true)) {
                throw ValidationException::withMessages([
                    'assignment' => __('This full-text assignment is no longer open for reviewer decisions.'),
                ]);
            }

            if (in_array($batch->status, [
                ProjectScreeningBatchStatus::Cancelled,
                ProjectScreeningBatchStatus::Completed,
            ], true)) {
                throw ValidationException::withMessages([
                    'assignment' => __('This full-text screening batch is not accepting new reviewer decisions.'),
                ]);
            }

            $artifact = $this->assertFullTextArtifact($assignment, $batch);
            $this->assertWorkBelongsToBatchSnapshot($batch, $assignment->work_id);

            $verdict = new ScreeningVerdict(
                id: (string) Str::uuid(),
                screeningRunId: $batch->screening_run_id,
                projectId: (string) $project->id,
                workId: (string) $assignment->work_id,
                stage: ScreeningStage::FULL_TEXT,
                decision: $screeningDecision,
                confidence: 1.0,
                source: 'human',
                rationale: new ScreeningRationale(
                    reason: $reason,
                    evidence: $this->normalizeList($evidence),
                    uncertainty: $this->normalizeList($uncertainty),
                    exclusionBasis: $exclusionBasis,
                ),
                decidedBy: (string) $actor->id,
                decidedAt: new \DateTimeImmutable,
                criteriaHash: $batch->criteria_hash,
                metadata: [
                    'batch_id' => $batch->id,
                    'assignment_id' => $assignment->id,
                    'source_full_text_batch_id' => $batch->source_full_text_batch_id,
                    'source_full_text_item_id' => $artifact->id,
                    'artifact_path' => $artifact->artifact_path,
                    'artifact_inspected' => true,
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
                'project.full_text_screening.decision_recorded',
                $assignment,
                $actor,
                $project->workspace,
                metadata: [
                    'batch_id' => $batch->id,
                    'work_id' => $assignment->work_id,
                    'decision' => $screeningDecision->value,
                    'screening_decision_id' => $verdict->id,
                    'source_full_text_item_id' => $artifact->id,
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

    private function assertFullTextArtifact(ProjectScreeningAssignment $assignment, ProjectScreeningBatch $batch): ProjectFullTextItem
    {
        $artifact = ProjectFullTextItem::query()
            ->whereKey($assignment->source_full_text_item_id)
            ->where('project_id', $assignment->project_id)
            ->where('batch_id', $batch->source_full_text_batch_id)
            ->where('work_id', $assignment->work_id)
            ->first();

        if (! $artifact instanceof ProjectFullTextItem) {
            throw ValidationException::withMessages([
                'assignment' => __('This assignment is missing its linked full-text artifact.'),
            ]);
        }

        if ($artifact->status !== ProjectFullTextItemStatus::Success || ! $artifact->artifact_path) {
            throw ValidationException::withMessages([
                'assignment' => __('Only successful full-text artifacts can be screened in this workflow slice.'),
            ]);
        }

        return $artifact;
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
                    'project.full_text_screening.conflict_created',
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
