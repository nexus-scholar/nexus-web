<?php

namespace Database\Factories;

use App\Enums\SearchPlanStatus;
use App\Models\Project;
use App\Models\ProjectSearchPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectSearchPlan>
 */
class ProjectSearchPlanFactory extends Factory
{
    protected $model = ProjectSearchPlan::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'status' => SearchPlanStatus::Draft,
            'version' => 1,
            'default_providers' => ['openalex', 'crossref'],
            'default_year_from' => null,
            'default_year_to' => null,
            'default_result_limit' => 50,
            'include_raw_data' => false,
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }
}
