<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    public function view(User $user, Workspace $workspace): bool
    {
        return $user->isOperator() || $user->belongsToWorkspace($workspace);
    }

    public function switch(User $user, Workspace $workspace): bool
    {
        return ! $workspace->isSuspended()
            && $user->belongsToWorkspace($workspace);
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return ! $workspace->isSuspended()
            && $user->isWorkspaceOwner($workspace);
    }

    public function manageMembers(User $user, Workspace $workspace): bool
    {
        return ! $workspace->isSuspended()
            && $workspace->isShared()
            && $user->canManageWorkspaceMembers($workspace);
    }
}
