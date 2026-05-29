<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\WorkspaceMembership;

class WorkspaceMembershipPolicy
{
    public function remove(User $user, WorkspaceMembership $membership): bool
    {
        $actorRole = $user->workspaceRole($membership->workspace);

        return $user->canManageWorkspaceMembers($membership->workspace)
            && ! $membership->isOwner()
            && ($actorRole === WorkspaceRole::Owner || ! $membership->isAdmin());
    }

    public function updateRole(User $user, WorkspaceMembership $membership): bool
    {
        return $user->isWorkspaceOwner($membership->workspace)
            && ! $membership->isOwner();
    }
}
