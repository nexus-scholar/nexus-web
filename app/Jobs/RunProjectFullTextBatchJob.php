<?php

namespace App\Jobs;

use App\Actions\Audit\RecordAuditEvent;
use App\Actions\Projects\RefreshProjectFullTextBatchCounts;
use App\Enums\ProjectFullTextBatchStatus;
use App\Enums\ProjectFullTextItemStatus;
use App\Models\ProjectFullTextBatch;
use App\Models\ProjectFullTextItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Nexus\Dissemination\Application\Dto\FullTextResult;
use Nexus\Dissemination\Application\UseCase\RetrieveFullText;
use Nexus\Dissemination\Application\UseCase\RetrieveFullTextHandler;
use Nexus\Dissemination\Domain\FullTextStatus;
use Nexus\Search\Domain\Port\WorkRepositoryPort;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Throwable;

class RunProjectFullTextBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly string $batchId,
    ) {}

    public function handle(
        RetrieveFullTextHandler $retrieveFullText,
        WorkRepositoryPort $works,
        RefreshProjectFullTextBatchCounts $refreshCounts,
        RecordAuditEvent $audit,
    ): void {
        $batch = ProjectFullTextBatch::query()
            ->with(['project.workspace', 'items'])
            ->findOrFail($this->batchId);

        if (in_array($batch->status, [
            ProjectFullTextBatchStatus::Completed,
            ProjectFullTextBatchStatus::CompletedWithFailures,
            ProjectFullTextBatchStatus::Cancelled,
        ], true)) {
            return;
        }

        $batch->forceFill([
            'status' => ProjectFullTextBatchStatus::Running,
            'started_at' => $batch->started_at ?? now(),
        ])->save();

        try {
            foreach ($batch->items as $item) {
                if ($item->status->isTerminal()) {
                    continue;
                }

                $this->runItem($batch, $item, $retrieveFullText, $works);
                $refreshCounts->handle($batch->refresh());
            }

            $batch = $refreshCounts->handle($batch->refresh(), completeIfTerminal: true);

            $audit->handle(
                'project.full_text.batch_completed',
                $batch,
                $batch->requester,
                $batch->project?->workspace,
                metadata: [
                    'batch_id' => $batch->id,
                    'status' => $batch->status->value,
                    'success_count' => $batch->success_count,
                    'failed_count' => $batch->failed_count,
                    'skipped_count' => $batch->skipped_count,
                    'manual_needed_count' => $batch->manual_needed_count,
                ],
                project: $batch->project,
            );
        } catch (Throwable $error) {
            $batch->forceFill([
                'status' => ProjectFullTextBatchStatus::Failed,
                'completed_at' => now(),
            ])->save();

            $audit->handle(
                'project.full_text.batch_failed',
                $batch,
                $batch->requester,
                $batch->project?->workspace,
                reason: $error->getMessage(),
                metadata: [
                    'batch_id' => $batch->id,
                    'error_class' => $error::class,
                ],
                project: $batch->project,
            );

            throw $error;
        }
    }

    private function runItem(
        ProjectFullTextBatch $batch,
        ProjectFullTextItem $item,
        RetrieveFullTextHandler $retrieveFullText,
        WorkRepositoryPort $works,
    ): void {
        $item->forceFill([
            'status' => ProjectFullTextItemStatus::Running,
            'started_at' => $item->started_at ?? now(),
            'error_message' => null,
        ])->save();

        $work = $works->findById(new WorkId(WorkIdNamespace::INTERNAL, $item->work_id));

        if ($work === null) {
            $this->applyResult($item, FullTextResult::skipped('Work is no longer available in the scholarly work repository.'));

            return;
        }

        try {
            $result = $retrieveFullText->handle(new RetrieveFullText(
                work: $work,
                destinationFolder: $batch->destination_folder,
                projectId: $batch->project_id,
            ));
        } catch (Throwable $error) {
            $result = FullTextResult::failure($error->getMessage(), metadata: [
                'error_class' => $error::class,
            ]);
        }

        $this->applyResult($item, $result);
    }

    private function applyResult(ProjectFullTextItem $item, FullTextResult $result): void
    {
        $status = match ($result->status) {
            FullTextStatus::SUCCESS => ProjectFullTextItemStatus::Success,
            FullTextStatus::FAILURE => ProjectFullTextItemStatus::Failed,
            FullTextStatus::SKIPPED => ProjectFullTextItemStatus::Skipped,
        };

        $metadata = array_filter([
            ...($item->metadata ?? []),
            ...$result->metadata,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $item->forceFill([
            'status' => $status,
            'source_alias' => $result->sourceAlias,
            'artifact_type' => $result->metadata['artifact_type'] ?? ($result->filePath ? 'pdf' : null),
            'artifact_path' => $result->filePath,
            'http_status' => $result->httpStatus,
            'error_message' => $result->errorMessage,
            'metadata' => $metadata === [] ? null : $metadata,
            'completed_at' => now(),
        ])->save();
    }
}
