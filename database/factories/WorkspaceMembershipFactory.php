<?php

namespace Database\Factories;

use App\Enums\WorkspaceMembershipStatus;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkspaceMembership>
 */
class WorkspaceMembershipFactory extends Factory
{
    protected $model = WorkspaceMembership::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'user_id' => User::factory(),
            'role' => WorkspaceRole::Member,
            'status' => WorkspaceMembershipStatus::Active,
            'joined_at' => now(),
            'removed_at' => null,
        ];
    }

    public function owner(): static
    {
        return $this->state(fn (): array => [
            'role' => WorkspaceRole::Owner,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (): array => [
            'role' => WorkspaceRole::Admin,
        ]);
    }

    public function removed(): static
    {
        return $this->state(fn (): array => [
            'status' => WorkspaceMembershipStatus::Removed,
            'removed_at' => now(),
        ]);
    }
}
