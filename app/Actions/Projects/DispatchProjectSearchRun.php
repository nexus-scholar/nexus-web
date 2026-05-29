<?php

namespace App\Actions\Projects;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\ProjectStatus;
use App\Enums\SearchRunStatus;
use App\Jobs\RunProjectSearchPlanJob;
use App\Models\Project;
use App\Models\ProjectSearchPlan;
use App\Models\ProjectSearchRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DispatchProjectSearchRun
{
    public function __construct(
        private readonly RecordAuditEvent $audit,
    ) {}

    public function handle(Project $project, ProjectSearchPlan $plan, User $actor): ProjectSearchRun
    {
        $plan->loadMissing('queries');

        if ($plan->queries->isEmpty()) {
            throw ValidationException::withMessages([
                'search_plan' => __('Add at least one query before running the search plan.'),
            ]);
        }

        $run = DB::transaction(function () use ($actor, $plan, $project): ProjectSearchRun {
            $run = $project->searchRuns()->create([
                'project_search_plan_id' => $plan->id,
                'status' => SearchRunStatus::Queued,
                'plan_version' => $plan->version,
                'query_count' => $plan->queries->count(),
                'failure_count' => 0,
                'total_raw' => 0,
                'total_unique' => 0,
                'metadata' => [
                    'default_providers' => $plan->default_providers ?? [],
                    'default_year_from' => $plan->default_year_from,
                    'default_year_to' => $plan->default_year_to,
                    'default_result_limit' => $plan->default_result_limit,
                    'include_raw_data' => $plan->include_raw_data,
                ],
                'requested_by' => $actor->id,
            ]);

            foreach ($plan->queries as $query) {
                $run->items()->create([
                    'project_search_plan_query_id' => $query->id,
                    'sort_order' => $query->sort_order,
                    'query_key' => $query->query_key,
                    'label' => $query->label,
                    'query' => $query->query,
                    'providers' => $query->providers ?? [],
                    'year_from' => $query->year_from,
                    'year_to' => $query->year_to,
                    'result_limit' => $query->result_limit,
                    'include_raw_data' => $query->include_raw_data,
                    'status' => SearchRunStatus::Queued,
                ]);
            }

            $project->update(['status' => ProjectStatus::Searching]);

            $this->audit->handle(
                'project.search_run.dispatched',
                $run,
                $actor,
                $project->workspace,
                metadata: [
                    'search_run_id' => $run->id,
                    'search_plan_id' => $plan->id,
                    'plan_version' => $plan->version,
                    'query_count' => $plan->queries->count(),
                ],
                project: $project,
            );

            return $run->load(['project', 'items']);
        });

        RunProjectSearchPlanJob::dispatch($run->id);

        return $run;
    }
}
