<?php

namespace App\Actions\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;

class SetCurrentWorkspace
{
    public function handle(User $user, Workspace $workspace): User
    {
        if ($workspace->isSuspended()) {
            throw new AuthorizationException('This workspace is suspended.');
        }

        if (! $user->belongsToWorkspace($workspace)) {
            throw new AuthorizationException('You do not belong to this workspace.');
        }

        $user->forceFill(['current_workspace_id' => $workspace->id])->save();

        return $user;
    }
}
