<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Enums\ReviewType;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        $name = fake()->sentence(4);

        return [
            'workspace_id' => Workspace::factory(),
            'owner_user_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'description' => fake()->optional()->paragraph(),
            'review_type' => ReviewType::SystematicReview,
            'status' => ProjectStatus::Draft,
            'metadata' => [],
            'locked_at' => null,
            'archived_at' => null,
        ];
    }

    public function readyForSearch(): static
    {
        return $this->state(fn (): array => [
            'status' => ProjectStatus::ReadyForSearch,
        ]);
    }

    public function locked(): static
    {
        return $this->state(fn (): array => [
            'status' => ProjectStatus::LockedCorpus,
            'locked_at' => now(),
            'lock_reason' => 'Test corpus lock.',
        ]);
    }
}
