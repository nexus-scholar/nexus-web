<?php

namespace App\Http\Controllers\Projects;

use App\Actions\Projects\DispatchProjectSearchRun;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectSearchRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Nexus\Shared\Port\JobLifecycleReaderPort;
use Nexus\Shared\ValueObject\JobLifecycleRecord;

class ProjectSearchRunController extends Controller
{
    public function store(
        Request $request,
        Project $project,
        DispatchProjectSearchRun $dispatchSearchRun,
    ): RedirectResponse {
        $project->load(['workspace', 'searchPlan.queries']);

        $this->authorize('runSearch', $project);

        $plan = $project->searchPlan()->with('queries')->firstOrFail();
        $run = $dispatchSearchRun->handle($project, $plan, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Search run queued.')]);

        return to_route('projects.search-runs.show', [$project, $run]);
    }

    public function show(
        Request $request,
        Project $project,
        ProjectSearchRun $searchRun,
        JobLifecycleReaderPort $lifecycleReader,
    ): Response {
        abort_unless($searchRun->project_id === $project->id, 404);

        $project->load('workspace');
        $searchRun->load(['requestedBy:id,name,email', 'items']);

        $this->authorize('viewSearchPlan', $project);

        return Inertia::render('projects/search-run', [
            'project' => $this->projectPayload($project),
            'searchRun' => $this->searchRunPayload($searchRun, $lifecycleReader),
        ]);
    }

    private function projectPayload(Project $project): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'status' => $project->status->value,
            'status_label' => $project->status->label(),
            'workspace' => [
                'id' => $project->workspace->id,
                'name' => $project->workspace->name,
            ],
            'urls' => [
                'overview' => route('projects.show', $project, absolute: false),
                'protocol' => route('projects.protocol.edit', $project, absolute: false),
                'search_plan' => route('projects.search-plan.edit', $project, absolute: false),
                'corpus' => route('projects.corpus.index', $project, absolute: false),
                'activity' => route('projects.activity.index', $project, absolute: false),
            ],
        ];
    }

    private function searchRunPayload(ProjectSearchRun $run, JobLifecycleReaderPort $lifecycleReader): array
    {
        $providerProgress = $this->providerProgress($run);

        return [
            'id' => $run->id,
            'status' => $run->status->value,
            'status_label' => $run->status->label(),
            'plan_version' => $run->plan_version,
            'query_count' => $run->query_count,
            'failure_count' => $run->failure_count,
            'total_raw' => $run->total_raw,
            'total_unique' => $run->total_unique,
            'error_message' => $run->error_message,
            'started_at' => $run->started_at?->toISOString(),
            'completed_at' => $run->completed_at?->toISOString(),
            'failed_at' => $run->failed_at?->toISOString(),
            'created_at' => $run->created_at?->toISOString(),
            'requested_by' => $run->requestedBy ? [
                'id' => $run->requestedBy->id,
                'name' => $run->requestedBy->name,
                'email' => $run->requestedBy->email,
            ] : null,
            'urls' => [
                'self' => route('projects.search-runs.show', [$run->project_id, $run], absolute: false),
            ],
            'items' => $run->items
                ->map(fn ($item): array => [
                    'id' => $item->id,
                    'query_key' => $item->query_key,
                    'label' => $item->label,
                    'query' => $item->query,
                    'providers' => $item->providers ?? [],
                    'year_from' => $item->year_from,
                    'year_to' => $item->year_to,
                    'result_limit' => $item->result_limit,
                    'include_raw_data' => $item->include_raw_data,
                    'status' => $item->status->value,
                    'status_label' => $item->status->label(),
                    'core_search_query_id' => $item->core_search_query_id,
                    'total_raw' => $item->total_raw,
                    'total_unique' => $item->total_unique,
                    'duration_ms' => $item->duration_ms,
                    'error_message' => $item->error_message,
                    'started_at' => $item->started_at?->toISOString(),
                    'completed_at' => $item->completed_at?->toISOString(),
                    'failed_at' => $item->failed_at?->toISOString(),
                    'provider_progress' => $providerProgress[$item->core_search_query_id] ?? [],
                ])
                ->values()
                ->all(),
            'lifecycle' => collect($lifecycleReader->forRun($run->id))
                ->map(fn (JobLifecycleRecord $record): array => [
                    'status' => $record->status->value,
                    'job_name' => $record->jobName,
                    'summary' => $record->summary,
                    'error_class' => $record->errorClass,
                    'error_message' => $record->errorMessage,
                    'duration_ms' => $record->durationMs,
                    'occurred_at' => $record->occurredAt->format(DATE_ATOM),
                ])
                ->values()
                ->all(),
        ];
    }

    private function providerProgress(ProjectSearchRun $run): array
    {
        $queryIds = $run->items
            ->pluck('core_search_query_id')
            ->filter()
            ->values();

        if ($queryIds->isEmpty()) {
            return [];
        }

        return DB::table('search_query_providers')
            ->whereIn('search_query_id', $queryIds)
            ->orderBy('provider_alias')
            ->get()
            ->groupBy('search_query_id')
            ->map(fn ($rows) => $rows
                ->map(fn ($row): array => [
                    'provider_alias' => (string) $row->provider_alias,
                    'total_raw' => (int) ($row->total_raw ?? $row->result_count ?? 0),
                    'total_unique' => (int) ($row->total_unique ?? $row->result_count ?? 0),
                    'duration_ms' => (int) ($row->duration_ms ?? $row->latency_ms ?? 0),
                    'error_message' => $row->error_message ?? $row->skip_reason ?? null,
                ])
                ->values()
                ->all())
            ->all();
    }
}
