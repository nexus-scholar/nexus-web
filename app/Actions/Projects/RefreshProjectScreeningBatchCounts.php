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
}
