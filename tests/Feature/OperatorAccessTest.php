<?php

namespace Tests\Feature;

use App\Actions\Workspaces\CreatePersonalWorkspace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperatorAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_disable_a_user_with_an_audit_reason(): void
    {
        $operator = User::factory()->operator()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($operator)->patch(route('operator.users.update', $user), [
            'status' => 'disabled',
            'reason' => 'Abuse review',
        ]);

        $response->assertRedirect();

        $user->refresh();

        $this->assertNotNull($user->disabled_at);
        $this->assertSame($operator->id, $user->disabled_by);
        $this->assertSame('Abuse review', $user->disabled_reason);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'user.disabled',
            'target_id' => (string) $user->id,
            'reason' => 'Abuse review',
        ]);
    }

    public function test_non_operator_cannot_access_operator_screens(): void
    {
        $user = User::factory()->create();
        app(CreatePersonalWorkspace::class)->handle($user);

        $response = $this->actingAs($user)->get(route('operator.users.index'));

        $response->assertForbidden();
    }

    public function test_operator_can_suspend_a_workspace_with_an_audit_reason(): void
    {
        $operator = User::factory()->operator()->create();
        $owner = User::factory()->create();
        $workspace = app(CreatePersonalWorkspace::class)->handle($owner);

        $response = $this->actingAs($operator)->patch(route('operator.workspaces.update', $workspace), [
            'status' => 'suspended',
            'reason' => 'Quota abuse',
        ]);

        $response->assertRedirect();

        $workspace->refresh();

        $this->assertNotNull($workspace->suspended_at);
        $this->assertSame($operator->id, $workspace->suspended_by);
        $this->assertSame('Quota abuse', $workspace->suspended_reason);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'workspace.suspended',
            'target_id' => $workspace->id,
            'reason' => 'Quota abuse',
        ]);
    }

    public function test_suspended_workspace_blocks_product_access_when_no_other_workspace_exists(): void
    {
        $operator = User::factory()->operator()->create();
        $user = User::factory()->create();
        $workspace = app(CreatePersonalWorkspace::class)->handle($user);

        $workspace->forceFill([
            'suspended_at' => now(),
            'suspended_by' => $operator->id,
            'suspended_reason' => 'Policy review',
        ])->save();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertForbidden();
    }
}
