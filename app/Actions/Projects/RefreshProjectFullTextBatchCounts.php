<?php

namespace App\Actions\Projects;

use App\Enums\ProjectFullTextBatchStatus;
use App\Enums\ProjectFullTextItemStatus;
use App\Models\ProjectFullTextBatch;
use App\Models\ProjectFullTextItem;

class RefreshProjectFullTextBatchCounts
{
    public function handle(ProjectFullTextBatch $batch, bool $completeIfTerminal = false): ProjectFullTextBatch
    {
        $counts = ProjectFullTextItem::query()
            ->where('batch_id', $batch->id)
            ->select('status')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();

        $candidateCount = (int) ProjectFullTextItem::query()
            ->where('batch_id', $batch->id)
            ->count();
        $success = $counts[ProjectFullTextItemStatus::Success->value] ?? 0;
        $failed = $counts[ProjectFullTextItemStatus::Failed->value] ?? 0;
        $skipped = $counts[ProjectFullTextItemStatus::Skipped->value] ?? 0;
        $manualNeeded = $counts[ProjectFullTextItemStatus::ManualNeeded->value] ?? 0;
        $terminalCount = $success + $failed + $skipped + $manualNeeded;

        $status = $batch->status;
        $completedAt = $batch->completed_at;

        if ($completeIfTerminal && $candidateCount > 0 && $terminalCount === $candidateCount) {
            $status = ($failed + $skipped + $manualNeeded) > 0
                ? ProjectFullTextBatchStatus::CompletedWithFailures
                : ProjectFullTextBatchStatus::Completed;
            $completedAt = $batch->completed_at ?? now();
        }

        $batch->forceFill([
            'status' => $status,
            'candidate_count' => $candidateCount,
            'success_count' => $success,
            'failed_count' => $failed,
            'skipped_count' => $skipped,
            'manual_needed_count' => $manualNeeded,
            'completed_at' => $completedAt,
        ])->save();

        return $batch->refresh();
    }
}
