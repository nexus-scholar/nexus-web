<?php

namespace Database\Factories;

use App\Enums\WorkspaceType;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Workspace>
 */
class WorkspaceFactory extends Factory
{
    protected $model = Workspace::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'type' => WorkspaceType::Shared,
            'owner_user_id' => User::factory(),
            'suspended_at' => null,
            'suspended_by' => null,
            'suspended_reason' => null,
        ];
    }

    public function personal(): static
    {
        return $this->state(fn (): array => [
            'type' => WorkspaceType::Personal,
        ]);
    }

    public function suspended(?User $operator = null, string $reason = 'Policy review'): static
    {
        return $this->state(fn (): array => [
            'suspended_at' => now(),
            'suspended_by' => $operator?->id,
            'suspended_reason' => $reason,
        ]);
    }
}
