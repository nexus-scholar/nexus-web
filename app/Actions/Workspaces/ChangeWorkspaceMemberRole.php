<?php

namespace App\Actions\Workspaces;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class ChangeWorkspaceMemberRole
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    public function handle(Workspace $workspace, User $target, WorkspaceRole $role, User $actor): WorkspaceMembership
    {
        if ($role === WorkspaceRole::Owner) {
            throw new AuthorizationException('Workspace ownership transfer is not available yet.');
        }

        $membership = $workspace->activeMembershipFor($target);

        if (! $membership || $membership->isOwner()) {
            throw new AuthorizationException('This workspace member cannot be changed.');
        }

        return DB::transaction(function () use ($actor, $membership, $role, $workspace): WorkspaceMembership {
            $previousRole = $membership->role;

            $membership->forceFill(['role' => $role])->save();

            $this->audit->handle(
                'workspace.role_changed',
                $membership,
                $actor,
                $workspace,
                metadata: [
                    'previous_role' => $previousRole->value,
                    'new_role' => $role->value,
                ],
            );

            return $membership;
        });
    }
}
