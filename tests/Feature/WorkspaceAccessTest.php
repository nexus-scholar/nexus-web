<?php

namespace Tests\Feature;

use App\Actions\Workspaces\CreatePersonalWorkspace;
use App\Actions\Workspaces\CreateSharedWorkspace;
use App\Enums\WorkspaceMembershipStatus;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_switch_to_an_active_workspace_membership(): void
    {
        $user = User::factory()->create();
        app(CreatePersonalWorkspace::class)->handle($user);
        $workspace = app(CreateSharedWorkspace::class)->handle($user, 'Evidence Lab');

        $response = $this->actingAs($user)->post(route('workspaces.switch'), [
            'workspace_id' => $workspace->id,
        ]);

        $response->assertRedirect();
        $this->assertSame($workspace->id, $user->fresh()->current_workspace_id);
    }

    public function test_user_cannot_switch_to_a_workspace_without_membership(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        app(CreatePersonalWorkspace::class)->handle($user);
        $workspace = app(CreateSharedWorkspace::class)->handle($other, 'Other Lab');

        $response = $this->actingAs($user)->post(route('workspaces.switch'), [
            'workspace_id' => $workspace->id,
        ]);

        $response->assertForbidden();
    }

    public function test_unverified_user_cannot_create_a_shared_workspace(): void
    {
        $user = User::factory()->unverified()->create();
        app(CreatePersonalWorkspace::class)->handle($user);

        $response = $this->actingAs($user)->post(route('workspaces.store'), [
            'name' => 'Unverified Lab',
        ]);

        $response->assertRedirect(route('verification.notice', absolute: false));
        $this->assertDatabaseMissing('workspaces', [
            'name' => 'Unverified Lab',
            'owner_user_id' => $user->id,
        ]);
    }

    public function test_workspace_owner_can_update_workspace_settings(): void
    {
        $user = User::factory()->create();
        $workspace = app(CreatePersonalWorkspace::class)->handle($user);

        $response = $this->actingAs($user)->patch(route('workspaces.settings.update', $workspace), [
            'name' => 'Updated Workspace',
        ]);

        $response->assertRedirect();
        $this->assertSame('Updated Workspace', $workspace->fresh()->name);
    }

    public function test_workspace_member_cannot_update_workspace_settings(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        app(CreatePersonalWorkspace::class)->handle($member);
        $workspace = app(CreateSharedWorkspace::class)->handle($owner, 'Shared Lab');
        $workspace->memberships()->create([
            'user_id' => $member->id,
            'role' => WorkspaceRole::Member,
            'status' => WorkspaceMembershipStatus::Active,
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($member)->patch(route('workspaces.settings.update', $workspace), [
            'name' => 'Blocked Rename',
        ]);

        $response->assertForbidden();
        $this->assertSame('Shared Lab', $workspace->fresh()->name);
    }

    public function test_personal_workspace_cannot_invite_members(): void
    {
        $user = User::factory()->create();
        $workspace = app(CreatePersonalWorkspace::class)->handle($user);

        $response = $this->actingAs($user)->post(route('workspaces.invitations.store', $workspace), [
            'email' => 'reviewer@example.edu',
            'role' => WorkspaceRole::Member->value,
        ]);

        $response->assertForbidden();
    }

    public function test_shared_workspace_owner_can_invite_a_member(): void
    {
        $owner = User::factory()->create();
        app(CreatePersonalWorkspace::class)->handle($owner);
        $workspace = app(CreateSharedWorkspace::class)->handle($owner, 'Shared Lab');

        $response = $this->actingAs($owner)->post(route('workspaces.invitations.store', $workspace), [
            'email' => 'reviewer@example.edu',
            'role' => WorkspaceRole::Member->value,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('workspace_invitations', [
            'workspace_id' => $workspace->id,
            'email' => 'reviewer@example.edu',
            'role' => WorkspaceRole::Member->value,
        ]);
    }

    public function test_workspace_admin_cannot_invite_another_admin(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        app(CreatePersonalWorkspace::class)->handle($admin);
        $workspace = app(CreateSharedWorkspace::class)->handle($owner, 'Shared Lab');
        $workspace->memberships()->create([
            'user_id' => $admin->id,
            'role' => WorkspaceRole::Admin,
            'status' => WorkspaceMembershipStatus::Active,
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(route('workspaces.invitations.store', $workspace), [
            'email' => 'admin2@example.edu',
            'role' => WorkspaceRole::Admin->value,
        ]);

        $response->assertForbidden();
    }

    public function test_invitation_acceptance_creates_an_active_membership(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'reviewer@example.edu']);
        app(CreatePersonalWorkspace::class)->handle($invitee);
        $workspace = app(CreateSharedWorkspace::class)->handle($owner, 'Shared Lab');
        $invitation = WorkspaceInvitation::factory()->create([
            'workspace_id' => $workspace->id,
            'email' => 'reviewer@example.edu',
            'role' => WorkspaceRole::Member,
            'invited_by' => $owner->id,
        ]);

        $response = $this->actingAs($invitee)->post(route('workspaces.invitations.accept', $invitation));

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas('workspace_memberships', [
            'workspace_id' => $workspace->id,
            'user_id' => $invitee->id,
            'role' => WorkspaceRole::Member->value,
            'status' => WorkspaceMembershipStatus::Active->value,
        ]);
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    public function test_removed_member_loses_workspace_access(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        app(CreatePersonalWorkspace::class)->handle($member);
        $workspace = app(CreateSharedWorkspace::class)->handle($owner, 'Shared Lab');
        $workspace->memberships()->create([
            'user_id' => $member->id,
            'role' => WorkspaceRole::Member,
            'status' => WorkspaceMembershipStatus::Active,
            'joined_at' => now(),
        ]);
        $member->forceFill(['current_workspace_id' => $workspace->id])->save();

        $response = $this->actingAs($owner)->delete(route('workspaces.members.destroy', [$workspace, $member]));

        $response->assertRedirect();
        $this->assertDatabaseHas('workspace_memberships', [
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
            'status' => WorkspaceMembershipStatus::Removed->value,
        ]);

        $this->actingAs($member)->get(route('workspaces.settings.edit', $workspace))->assertForbidden();
    }

    public function test_disabled_user_cannot_access_product_pages(): void
    {
        $operator = User::factory()->operator()->create();
        $user = User::factory()->disabled($operator)->create();
        app(CreatePersonalWorkspace::class)->handle($user);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertForbidden();
    }
}
