<?php

namespace Database\Factories;

use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditEvent>
 */
class AuditEventFactory extends Factory
{
    protected $model = AuditEvent::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'project_id' => null,
            'actor_user_id' => User::factory(),
            'event_type' => 'workspace.created',
            'target_type' => Workspace::class,
            'target_id' => fake()->uuid(),
            'reason' => null,
            'metadata' => [],
            'occurred_at' => now(),
        ];
    }
}
