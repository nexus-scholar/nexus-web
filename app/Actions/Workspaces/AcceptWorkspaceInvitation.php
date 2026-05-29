<?php

namespace App\Actions\Workspaces;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\WorkspaceMembershipStatus;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMembership;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class AcceptWorkspaceInvitation
{
    public function __construct(
        private readonly RecordAuditEvent $audit,
        private readonly SetCurrentWorkspace $setCurrentWorkspace,
    ) {}

    public function handle(WorkspaceInvitation $invitation, User $user): WorkspaceMembership
    {
        if (! $invitation->isPending()) {
            throw new AuthorizationException('This invitation is no longer available.');
        }

        if (strcasecmp($invitation->email, $user->email) !== 0) {
            throw new AuthorizationException('This invitation belongs to another email address.');
        }

        return DB::transaction(function () use ($invitation, $user): WorkspaceMembership {
            $membership = WorkspaceMembership::updateOrCreate(
                [
                    'workspace_id' => $invitation->workspace_id,
                    'user_id' => $user->id,
                ],
                [
                    'role' => $invitation->role,
                    'status' => WorkspaceMembershipStatus::Active,
                    'joined_at' => now(),
                    'removed_at' => null,
                ],
            );

            $invitation->forceFill([
                'accepted_by' => $user->id,
                'accepted_at' => now(),
            ])->save();

            if ($user->current_workspace_id === null) {
                $this->setCurrentWorkspace->handle($user, $invitation->workspace);
            }

            $this->audit->handle(
                'workspace.member_joined',
                $membership,
                $user,
                $invitation->workspace,
                metadata: ['invitation_id' => $invitation->id],
            );

            return $membership;
        });
    }
}
