<?php

namespace App\Actions\Projects;

use App\Enums\SearchPlanStatus;
use App\Models\Project;
use App\Models\ProjectProtocol;
use App\Models\ProjectSearchPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EnsureProjectSearchPlan
{
    public function handle(Project $project, ?User $actor = null): ProjectSearchPlan
    {
        $project->loadMissing('protocol');
        $protocol = $project->protocol()->firstOrFail();

        return DB::transaction(function () use ($actor, $project, $protocol): ProjectSearchPlan {
            $plan = ProjectSearchPlan::firstOrCreate(
                ['project_id' => $project->id],
                [
                    'status' => SearchPlanStatus::Draft,
                    'version' => 1,
                    'default_providers' => $protocol->target_providers ?? [],
                    'default_year_from' => $this->yearFromProtocolStart($protocol),
                    'default_year_to' => $this->yearFromProtocolEnd($protocol),
                    'default_result_limit' => 50,
                    'include_raw_data' => false,
                    'created_by' => $actor?->id,
                    'updated_by' => $actor?->id,
                ],
            );

            if ($plan->queries()->doesntExist()) {
                $plan->queries()->create([
                    'sort_order' => 1,
                    'query_key' => 'primary-search',
                    'label' => 'Primary search',
                    'query' => $protocol->research_question ?: $project->name,
                    'providers' => $plan->default_providers ?? [],
                    'year_from' => $plan->default_year_from,
                    'year_to' => $plan->default_year_to,
                    'result_limit' => $plan->default_result_limit,
                    'include_raw_data' => $plan->include_raw_data,
                ]);
            }

            return $plan->refresh()->load('queries');
        });
    }

    private function yearFromProtocolStart(ProjectProtocol $protocol): ?int
    {
        return $protocol->no_date_limit ? null : $protocol->date_range_start?->year;
    }

    private function yearFromProtocolEnd(ProjectProtocol $protocol): ?int
    {
        return $protocol->no_date_limit ? null : $protocol->date_range_end?->year;
    }
}
