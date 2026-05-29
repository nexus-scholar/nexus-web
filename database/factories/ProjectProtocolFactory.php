<?php

namespace Database\Factories;

use App\Enums\ProtocolStatus;
use App\Models\Project;
use App\Models\ProjectProtocol;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectProtocol>
 */
class ProjectProtocolFactory extends Factory
{
    protected $model = ProjectProtocol::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'status' => ProtocolStatus::Draft,
            'version' => 1,
            'title' => fake()->sentence(4),
            'research_question' => fake()->optional()->sentence(12),
            'background' => fake()->optional()->paragraph(),
            'inclusion_criteria' => fake()->optional()->paragraph(),
            'exclusion_criteria' => fake()->optional()->paragraph(),
            'target_providers' => ['openalex'],
            'date_range_start' => null,
            'date_range_end' => null,
            'no_date_limit' => true,
            'language_policy' => 'English-language records.',
            'min_reviewer_count' => 2,
            'ai_screening_policy' => 'human_only',
            'full_text_policy' => 'optional',
            'created_by' => User::factory(),
            'updated_by' => null,
            'completed_at' => null,
            'locked_at' => null,
        ];
    }

    public function complete(): static
    {
        return $this->state(fn (): array => [
            'status' => ProtocolStatus::Complete,
            'completed_at' => now(),
        ]);
    }
}
