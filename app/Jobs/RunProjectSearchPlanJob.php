<?php

namespace App\Jobs;

use App\Enums\ProjectStatus;
use App\Enums\SearchRunStatus;
use App\Models\ProjectSearchRun;
use App\Models\ProjectSearchRunItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Nexus\Search\Application\Port\SearchExecutorPort;
use Nexus\Search\Application\UseCase\SearchAcrossProviders;
use Nexus\Shared\Port\JobLifecycleRecorderPort;
use Nexus\Shared\ValueObject\JobLifecycleRecord;
use Throwable;

class RunProjectSearchPlanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly string $searchRunId,
    ) {}

    public function handle(SearchExecutorPort $executor, JobLifecycleRecorderPort $lifecycle): void
    {
        $run = ProjectSearchRun::query()
            ->with(['project.workspace', 'items'])
            ->findOrFail($this->searchRunId);

        if ($run->status === SearchRunStatus::Completed) {
            return;
        }

        $startedAt = hrtime(true);
        $run->update([
            'status' => SearchRunStatus::Running,
            'started_at' => $run->started_at ?? now(),
            'failed_at' => null,
            'error_message' => null,
        ]);

        $lifecycle->record(JobLifecycleRecord::started(
            runId: $run->id,
            jobName: 'project-search-run',
            jobClass: self::class,
            context: $this->lifecycleContext($run),
        ));

        foreach ($run->items as $item) {
            if ($item->status === SearchRunStatus::Completed) {
                continue;
            }

            $this->runItem($run, $item, $executor, $lifecycle);
        }

        $run->refresh()->load('items', 'project');
        $failureCount = $run->items->where('status', SearchRunStatus::Failed)->count();
        $completedCount = $run->items->where('status', SearchRunStatus::Completed)->count();
        $totalRaw = (int) $run->items->sum('total_raw');
        $totalUnique = (int) $run->items->sum('total_unique');

        if ($completedCount === 0 && $failureCount > 0) {
            $run->update([
                'status' => SearchRunStatus::Failed,
                'failure_count' => $failureCount,
                'total_raw' => $totalRaw,
                'total_unique' => $totalUnique,
                'failed_at' => now(),
                'error_message' => $run->items->firstWhere('status', SearchRunStatus::Failed)?->error_message,
            ]);
            $run->project?->update(['status' => ProjectStatus::ReadyForSearch]);

            $lifecycle->record(JobLifecycleRecord::failed(
                runId: $run->id,
                jobName: 'project-search-run',
                jobClass: self::class,
                context: $this->lifecycleContext($run),
                errorClass: 'SearchRunFailed',
                errorMessage: 'Every search-plan query failed.',
                durationMs: $this->elapsedMs($startedAt),
            ));

            return;
        }

        $run->update([
            'status' => SearchRunStatus::Completed,
            'failure_count' => $failureCount,
            'total_raw' => $totalRaw,
            'total_unique' => $totalUnique,
            'completed_at' => now(),
        ]);
        $run->project?->update(['status' => ProjectStatus::DraftCorpus]);

        $lifecycle->record(JobLifecycleRecord::completed(
            runId: $run->id,
            jobName: 'project-search-run',
            jobClass: self::class,
            context: $this->lifecycleContext($run),
            summary: [
                'query_count' => $run->query_count,
                'completed_count' => $completedCount,
                'failure_count' => $failureCount,
                'total_raw' => $totalRaw,
                'total_unique' => $totalUnique,
            ],
            durationMs: $this->elapsedMs($startedAt),
        ));
    }

    private function runItem(
        ProjectSearchRun $run,
        ProjectSearchRunItem $item,
        SearchExecutorPort $executor,
        JobLifecycleRecorderPort $lifecycle,
    ): void {
        $itemStartedAt = hrtime(true);
        $command = new SearchAcrossProviders(
            query: $item->query,
            projectId: $run->project_id,
            maxResults: $item->result_limit,
            yearFrom: $item->year_from,
            yearTo: $item->year_to,
            providerAliases: $item->providers ?? [],
            includeRawData: $item->include_raw_data,
        );

        $item->update([
            'status' => SearchRunStatus::Running,
            'started_at' => $item->started_at ?? now(),
            'failed_at' => null,
            'error_message' => null,
            'core_search_query_id' => $command->query->id,
        ]);

        try {
            $result = $executor->handle($command);

            $item->update([
                'status' => SearchRunStatus::Completed,
                'total_raw' => $result->totalRaw,
                'total_unique' => $result->corpus->count(),
                'duration_ms' => $result->durationMs,
                'completed_at' => now(),
            ]);

            $lifecycle->record(JobLifecycleRecord::progressed(
                runId: $run->id,
                jobName: 'project-search-run',
                jobClass: self::class,
                progressKey: 'item:'.$item->query_key,
                context: $this->lifecycleContext($run, $item),
                summary: [
                    'query_key' => $item->query_key,
                    'status' => SearchRunStatus::Completed->value,
                    'total_raw' => $result->totalRaw,
                    'total_unique' => $result->corpus->count(),
                    'core_search_query_id' => $command->query->id,
                ],
                durationMs: $this->elapsedMs($itemStartedAt),
            ));
        } catch (Throwable $error) {
            $item->update([
                'status' => SearchRunStatus::Failed,
                'duration_ms' => $this->elapsedMs($itemStartedAt),
                'error_message' => $error->getMessage(),
                'failed_at' => now(),
            ]);

            $lifecycle->record(JobLifecycleRecord::progressed(
                runId: $run->id,
                jobName: 'project-search-run',
                jobClass: self::class,
                progressKey: 'item:'.$item->query_key,
                context: $this->lifecycleContext($run, $item),
                summary: [
                    'query_key' => $item->query_key,
                    'status' => SearchRunStatus::Failed->value,
                    'error_class' => $error::class,
                    'error_message' => $error->getMessage(),
                    'core_search_query_id' => $command->query->id,
                ],
                durationMs: $this->elapsedMs($itemStartedAt),
            ));
        }
    }

    private function lifecycleContext(ProjectSearchRun $run, ?ProjectSearchRunItem $item = null): array
    {
        return [
            'project_id' => $run->project_id,
            'search_run_id' => $run->id,
            'search_plan_id' => $run->project_search_plan_id,
            'query_key' => $item?->query_key,
            'core_search_query_id' => $item?->core_search_query_id,
        ];
    }

    private function elapsedMs(float|int $startNs): int
    {
        return (int) round((hrtime(true) - $startNs) / 1_000_000);
    }
}
