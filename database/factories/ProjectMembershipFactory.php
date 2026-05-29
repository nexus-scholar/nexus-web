<?php

namespace Database\Factories;

use App\Enums\ProjectMembershipStatus;
use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\ProjectMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectMembership>
 */
class ProjectMembershipFactory extends Factory
{
    protected $model = ProjectMembership::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'user_id' => User::factory(),
            'role' => ProjectRole::Reviewer,
            'status' => ProjectMembershipStatus::Active,
            'joined_at' => now(),
            'removed_at' => null,
        ];
    }

    public function owner(): static
    {
        return $this->state(fn (): array => [
            'role' => ProjectRole::Owner,
        ]);
    }

    public function adjudicator(): static
    {
        return $this->state(fn (): array => [
            'role' => ProjectRole::Adjudicator,
        ]);
    }

    public function viewer(): static
    {
        return $this->state(fn (): array => [
            'role' => ProjectRole::Viewer,
        ]);
    }

    public function removed(): static
    {
        return $this->state(fn (): array => [
            'status' => ProjectMembershipStatus::Removed,
            'removed_at' => now(),
        ]);
    }
}
