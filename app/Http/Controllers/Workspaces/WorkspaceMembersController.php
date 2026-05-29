<?php

namespace App\Http\Controllers\Workspaces;

use App\Actions\Workspaces\ChangeWorkspaceMemberRole;
use App\Actions\Workspaces\RemoveWorkspaceMember;
use App\Enums\WorkspaceMembershipStatus;
use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceMembersController extends Controller
{
    public function index(Request $request, Workspace $workspace): Response
    {
        $this->authorize('view', $workspace);

        $actor = $request->user();

        return Inertia::render('workspaces/members', [
            'selectedWorkspace' => $this->workspacePayload($workspace, $request),
            'members' => $workspace->memberships()
                ->with('user:id,name,email,email_verified_at')
                ->where('status', WorkspaceMembershipStatus::Active->value)
                ->oldest()
                ->get()
                ->map(fn ($membership) => [
                    'id' => $membership->id,
                    'role' => $membership->role->value,
                    'role_label' => $membership->role->label(),
                    'joined_at' => $membership->joined_at?->toISOString(),
                    'user' => [
                        'id' => $membership->user->id,
                        'name' => $membership->user->name,
                        'email' => $membership->user->email,
                        'email_verified_at' => $membership->user->email_verified_at?->toISOString(),
                    ],
                    'can_remove' => $actor->can('remove', $membership),
                    'can_update_role' => $actor->can('updateRole', $membership),
                ]),
            'invitations' => $workspace->invitations()
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->latest()
                ->get()
                ->map(fn ($invitation) => [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'role' => $invitation->role->value,
                    'role_label' => $invitation->role->label(),
                    'expires_at' => $invitation->expires_at->toISOString(),
                ]),
            'available_roles' => $actor->isWorkspaceOwner($workspace)
                ? [WorkspaceRole::Admin->value, WorkspaceRole::Member->value]
                : [WorkspaceRole::Member->value],
            'can' => [
                'manage_members' => $actor->can('manageMembers', $workspace),
            ],
        ]);
    }

    public function destroy(
        Request $request,
        Workspace $workspace,
        User $user,
        RemoveWorkspaceMember $removeMember,
    ): RedirectResponse {
        $membership = $workspace->activeMembershipFor($user);

        abort_if(! $membership, 404);

        $this->authorize('remove', $membership);

        $removeMember->handle($workspace, $user, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member removed.')]);

        return back();
    }

    public function update(
        Request $request,
        Workspace $workspace,
        User $user,
        ChangeWorkspaceMemberRole $changeRole,
    ): RedirectResponse {
        $membership = $workspace->activeMembershipFor($user);

        abort_if(! $membership, 404);

        $this->authorize('updateRole', $membership);

        $data = $request->validate([
            'role' => ['required', Rule::in([WorkspaceRole::Admin->value, WorkspaceRole::Member->value])],
        ]);

        $changeRole->handle($workspace, $user, WorkspaceRole::from($data['role']), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member role updated.')]);

        return back();
    }

    private function workspacePayload(Workspace $workspace, Request $request): array
    {
        return [
            'id' => $workspace->id,
            'name' => $workspace->name,
            'slug' => $workspace->slug,
            'type' => $workspace->type->value,
            'role' => $request->user()->workspaceRole($workspace)?->value,
            'suspended_at' => $workspace->suspended_at?->toISOString(),
            'settings_url' => route('workspaces.settings.edit', $workspace, absolute: false),
            'members_url' => route('workspaces.members.index', $workspace, absolute: false),
        ];
    }
}
