<?php

namespace Database\Factories;

use App\Enums\SearchRunStatus;
use App\Models\Project;
use App\Models\ProjectSearchPlan;
use App\Models\ProjectSearchRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectSearchRun>
 */
class ProjectSearchRunFactory extends Factory
{
    protected $model = ProjectSearchRun::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'project_search_plan_id' => ProjectSearchPlan::factory(),
            'status' => SearchRunStatus::Queued,
            'plan_version' => 1,
            'query_count' => 1,
            'failure_count' => 0,
            'total_raw' => 0,
            'total_unique' => 0,
            'metadata' => [],
            'requested_by' => User::factory(),
            'started_at' => null,
            'completed_at' => null,
            'failed_at' => null,
        ];
    }
}
