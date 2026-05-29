<?php

namespace Tests\Feature\Auth;

use App\Enums\WorkspaceMembershipStatus;
use App\Enums\WorkspaceRole;
use App\Enums\WorkspaceType;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'test@example.com')->firstOrFail();
        $workspace = Workspace::where('owner_user_id', $user->id)->firstOrFail();

        $this->assertFalse($user->hasVerifiedEmail());
        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('verification.notice', absolute: false));

        $this->assertSame($workspace->id, $user->current_workspace_id);
        $this->assertSame(WorkspaceType::Personal, $workspace->type);
        $this->assertDatabaseHas('workspace_memberships', [
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceRole::Owner->value,
            'status' => WorkspaceMembershipStatus::Active->value,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'user.registered',
            'target_id' => (string) $user->id,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'workspace.created',
            'target_id' => $workspace->id,
        ]);
    }
}
