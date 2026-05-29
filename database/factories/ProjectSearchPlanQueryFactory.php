<?php

namespace Database\Factories;

use App\Models\ProjectSearchPlan;
use App\Models\ProjectSearchPlanQuery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectSearchPlanQuery>
 */
class ProjectSearchPlanQueryFactory extends Factory
{
    protected $model = ProjectSearchPlanQuery::class;

    public function definition(): array
    {
        return [
            'project_search_plan_id' => ProjectSearchPlan::factory(),
            'sort_order' => 1,
            'query_key' => 'primary-search',
            'label' => fake()->sentence(4),
            'query' => fake()->sentence(10),
            'providers' => ['openalex', 'crossref'],
            'year_from' => null,
            'year_to' => null,
            'result_limit' => 50,
            'include_raw_data' => false,
        ];
    }
}
