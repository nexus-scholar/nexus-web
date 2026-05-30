<?php

namespace App\Actions\Projects;

use App\Enums\ProjectScreeningAssignmentStatus;
use App\Enums\ProjectScreeningBatchStatus;
use App\Enums\ProjectScreeningConflictStatus;
use App\Models\ProjectScreeningAssignment;
use App\Models\ProjectScreeningBatch;
use App\Models\ProjectScreeningConflict;
use Illuminate\Support\Facades\DB;
use Nexus\Screening\Application\Port\ScreeningRunRepositoryPort;
use Nexus\Screening\Domain\ScreeningDecision;

class RefreshProjectScreeningBatchCounts
{
    public function __construct(
        private readonly ScreeningRunRepositoryPort $screeningRuns,
    ) {}

    public function handle(ProjectScreeningBatch $batch): ProjectScreeningBatch
    {
        $assignmentCounts = ProjectScreeningAssignment::query()
            ->where('batch_id', $batch->id)
            ->select('status')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();

        $decisionIds = ProjectScreeningAssignment::query()
            ->where('batch_id', $batch->id)
            ->whereNotNull('screening_decision_id')
            ->pluck('screening_decision_id')
            ->all();

        $decisionCounts = $decisionIds === []
            ? []
            : DB::table('screening_decisions')
                ->whereIn('id', $decisionIds)
                ->select('decision')
                ->selectRaw('count(*) as aggregate')
                ->groupBy('decision')
                ->pluck('aggregate', 'decision')
                ->map(fn (mixed $count): int => (int) $count)
                ->all();

        $openConflicts = ProjectScreeningConflict::query()
            ->where('batch_id', $batch->id)
            ->where('status', ProjectScreeningConflictStatus::Open->value)
            ->count();

        $resolvedConflicts = ProjectScreeningConflict::query()
            ->where('batch_id', $batch->id)
            ->where('status', ProjectScreeningConflictStatus::Resolved->value)
            ->count();
        $outcomes = $this->outcomeCounts($batch);

        $totalAssignments = array_sum($assignmentCounts);
        $unresolvedAssignments =
            ($assignmentCounts[ProjectScreeningAssignmentStatus::Pending->value] ?? 0)
            + ($assignmentCounts[ProjectScreeningAssignmentStatus::InProgress->value] ?? 0)
            + ($assignmentCounts[ProjectScreeningAssignmentStatus::Decided->value] ?? 0)
            + ($assignmentCounts[ProjectScreeningAssignmentStatus::Conflict->value] ?? 0);

        $status = match (true) {
            $openConflicts > 0 => ProjectScreeningBatchStatus::Conflicts,
            $totalAssignments > 0 && $unresolvedAssignments === 0 => ProjectScreeningBatchStatus::Completed,
            default => ProjectScreeningBatchStatus::Active,
        };

        $counts = [
            'assignments' => [
                'total' => $totalAssignments,
                'pending' => $assignmentCounts[ProjectScreeningAssignmentStatus::Pending->value] ?? 0,
                'in_progress' => $assignmentCounts[ProjectScreeningAssignmentStatus::InProgress->value] ?? 0,
                'decided' => $assignmentCounts[ProjectScreeningAssignmentStatus::Decided->value] ?? 0,
                'conflict' => $assignmentCounts[ProjectScreeningAssignmentStatus::Conflict->value] ?? 0,
                'resolved' => $assignmentCounts[ProjectScreeningAssignmentStatus::Resolved->value] ?? 0,
            ],
            'decisions' => [
                ScreeningDecision::INCLUDE->value => $decisionCounts[ScreeningDecision::INCLUDE->value] ?? 0,
                ScreeningDecision::NEEDS_REVIEW->value => $decisionCounts[ScreeningDecision::NEEDS_REVIEW->value] ?? 0,
                ScreeningDecision::EXCLUDE->value => $decisionCounts[ScreeningDecision::EXCLUDE->value] ?? 0,
            ],
            'conflicts' => [
                'open' => $openConflicts,
                'resolved' => $resolvedConflicts,
            ],
            'outcomes' => $outcomes,
        ];

        $batch->forceFill([
            'status' => $status,
            'counts' => $counts,
            'completed_at' => $status === ProjectScreeningBatchStatus::Completed
                ? ($batch->completed_at ?? now())
                : null,
        ])->save();

        if ($status === ProjectScreeningBatchStatus::Completed && $batch->screening_run_id) {
            $this->screeningRuns->complete($batch->screening_run_id, $counts);
        }

        return $batch->refresh();
    }

    /**
     * @return array<string, int>
     */
    private function outcomeCounts(ProjectScreeningBatch $batch): array
    {
        $assignmentsByWork = DB::table('project_screening_assignments')
            ->where('batch_id', $batch->id)
            ->get(['work_id', 'status', 'screening_decision_id'])
            ->groupBy('work_id');

        $resolvedConflictDecisionIds = ProjectScreeningConflict::query()
            ->where('batch_id', $batch->id)
            ->where('status', ProjectScreeningConflictStatus::Resolved->value)
            ->whereNotNull('resolved_decision_id')
            ->pluck('resolved_decision_id', 'work_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();

        $decisionIds = $assignmentsByWork
            ->flatMap(fn ($assignments) => $assignments->pluck('screening_decision_id'))
            ->merge(array_values($resolvedConflictDecisionIds))
            ->filter()
            ->map(fn (mixed $id): string => (string) $id)
            ->unique()
            ->values()
            ->all();

        $decisionsById = $decisionIds === []
            ? []
            : DB::table('screening_decisions')
                ->whereIn('id', $decisionIds)
                ->pluck('decision', 'id')
                ->map(fn (mixed $decision): string => (string) $decision)
                ->all();

        $decisionOutcomes = [
            ScreeningDecision::INCLUDE->value => 0,
            ScreeningDecision::NEEDS_REVIEW->value => 0,
            ScreeningDecision::EXCLUDE->value => 0,
        ];
        $resolvedWorks = 0;

        foreach ($assignmentsByWork as $workId => $assignments) {
            $finalDecision = null;
            $resolvedConflictDecisionId = $resolvedConflictDecisionIds[(string) $workId] ?? null;

            if ($resolvedConflictDecisionId) {
                $finalDecision = $decisionsById[$resolvedConflictDecisionId] ?? null;
            } else {
                $linkedDecisionIds = $assignments
                    ->pluck('screening_decision_id')
                    ->filter()
                    ->map(fn (mixed $id): string => (string) $id)
                    ->values();
                $allAssignmentsResolved = $assignments->isNotEmpty()
                    && $assignments->every(
                        fn (object $assignment): bool => (string) $assignment->status === ProjectScreeningAssignmentStatus::Resolved->value,
                    );

                if ($allAssignmentsResolved && $linkedDecisionIds->count() >= $batch->required_reviewer_count) {
                    $uniqueDecisions = $linkedDecisionIds
                        ->map(fn (string $id): ?string => $decisionsById[$id] ?? null)
                        ->filter()
                        ->unique()
                        ->values();

                    if ($uniqueDecisions->count() === 1) {
                        $finalDecision = $uniqueDecisions->first();
                    }
                }
            }

            if (is_string($finalDecision) && array_key_exists($finalDecision, $decisionOutcomes)) {
                $decisionOutcomes[$finalDecision]++;
                $resolvedWorks++;
            }
        }

        $totalWorks = $assignmentsByWork->count();

        return [
            'total_works' => $totalWorks,
            'resolved_works' => $resolvedWorks,
            'unresolved_works' => max(0, $totalWorks - $resolvedWorks),
            ScreeningDecision::INCLUDE->value => $decisionOutcomes[ScreeningDecision::INCLUDE->value],
            ScreeningDecision::NEEDS_REVIEW->value => $decisionOutcomes[ScreeningDecision::NEEDS_REVIEW->value],
            ScreeningDecision::EXCLUDE->value => $decisionOutcomes[ScreeningDecision::EXCLUDE->value],
            'ready_for_full_text' => $decisionOutcomes[ScreeningDecision::INCLUDE->value]
                + $decisionOutcomes[ScreeningDecision::NEEDS_REVIEW->value],
            'excluded' => $decisionOutcomes[ScreeningDecision::EXCLUDE->value],
        ];
    }
}
