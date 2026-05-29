<?php

namespace Database\Factories;

use App\Enums\SearchRunStatus;
use App\Models\ProjectSearchPlanQuery;
use App\Models\ProjectSearchRun;
use App\Models\ProjectSearchRunItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectSearchRunItem>
 */
class ProjectSearchRunItemFactory extends Factory
{
    protected $model = ProjectSearchRunItem::class;

    public function definition(): array
    {
        return [
            'project_search_run_id' => ProjectSearchRun::factory(),
            'project_search_plan_query_id' => ProjectSearchPlanQuery::factory(),
            'sort_order' => 1,
            'query_key' => 'primary-search',
            'label' => fake()->sentence(4),
            'query' => fake()->sentence(10),
            'providers' => ['openalex', 'crossref'],
            'year_from' => null,
            'year_to' => null,
            'result_limit' => 50,
            'include_raw_data' => false,
            'status' => SearchRunStatus::Queued,
            'core_search_query_id' => null,
            'total_raw' => 0,
            'total_unique' => 0,
            'duration_ms' => null,
        ];
    }
}
