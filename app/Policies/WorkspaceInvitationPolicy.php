<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkspaceInvitation;

class WorkspaceInvitationPolicy
{
    public function accept(User $user, WorkspaceInvitation $invitation): bool
    {
        return $invitation->isPending()
            && strcasecmp($user->email, $invitation->email) === 0;
    }
}
