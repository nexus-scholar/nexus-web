<?php

namespace App\Actions\Projects;

use App\Actions\Audit\RecordAuditEvent;
use App\Models\Project;
use App\Models\ProjectSearchPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateProjectSearchPlan
{
    public function __construct(
        private readonly RecordAuditEvent $audit,
    ) {}

    public function handle(Project $project, ProjectSearchPlan $plan, User $actor, array $data): ProjectSearchPlan
    {
        return DB::transaction(function () use ($actor, $data, $plan, $project): ProjectSearchPlan {
            $plan->update([
                'version' => $plan->version + 1,
                'default_providers' => $data['default_providers'],
                'default_year_from' => $data['default_year_from'],
                'default_year_to' => $data['default_year_to'],
                'default_result_limit' => $data['default_result_limit'],
                'include_raw_data' => $data['include_raw_data'],
                'updated_by' => $actor->id,
            ]);

            $plan->queries()->delete();

            foreach (array_values($data['queries']) as $index => $query) {
                $plan->queries()->create([
                    'sort_order' => $index + 1,
                    'query_key' => $query['query_key'],
                    'label' => $query['label'],
                    'query' => $query['query'],
                    'providers' => $query['providers'],
                    'year_from' => $query['year_from'],
                    'year_to' => $query['year_to'],
                    'result_limit' => $query['result_limit'],
                    'include_raw_data' => $query['include_raw_data'],
                ]);
            }

            $this->audit->handle(
                'project.search_plan.updated',
                $plan,
                $actor,
                $project->workspace,
                metadata: [
                    'version' => $plan->version,
                    'query_count' => count($data['queries']),
                    'default_providers' => $data['default_providers'],
                ],
                project: $project,
            );

            return $plan->refresh()->load('queries');
        });
    }
}
