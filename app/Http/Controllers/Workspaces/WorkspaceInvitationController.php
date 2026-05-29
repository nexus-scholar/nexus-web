<?php

namespace App\Http\Controllers\Workspaces;

use App\Actions\Workspaces\AcceptWorkspaceInvitation;
use App\Actions\Workspaces\InviteWorkspaceMember;
use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class WorkspaceInvitationController extends Controller
{
    public function store(Request $request, Workspace $workspace, InviteWorkspaceMember $inviteMember): RedirectResponse
    {
        $this->authorize('manageMembers', $workspace);

        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'role' => ['required', Rule::in([WorkspaceRole::Admin->value, WorkspaceRole::Member->value])],
        ]);

        $inviteMember->handle($workspace, $request->user(), $data['email'], WorkspaceRole::from($data['role']));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation created.')]);

        return back();
    }

    public function accept(
        Request $request,
        WorkspaceInvitation $invitation,
        AcceptWorkspaceInvitation $acceptInvitation,
    ): RedirectResponse {
        $this->authorize('accept', $invitation);

        $acceptInvitation->handle($invitation, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Workspace invitation accepted.')]);

        return to_route('dashboard');
    }
}
