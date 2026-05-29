<?php

namespace App\Actions\Workspaces;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class InviteWorkspaceMember
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    public function handle(Workspace $workspace, User $inviter, string $email, WorkspaceRole $role): WorkspaceInvitation
    {
        if ($workspace->isPersonal()) {
            throw new AuthorizationException('Personal workspaces cannot invite members.');
        }

        if ($role === WorkspaceRole::Owner) {
            throw new AuthorizationException('Workspace owners cannot be invited.');
        }

        if ($role === WorkspaceRole::Admin && ! $inviter->isWorkspaceOwner($workspace)) {
            throw new AuthorizationException('Only workspace owners can invite admins.');
        }

        return DB::transaction(function () use ($email, $inviter, $role, $workspace): WorkspaceInvitation {
            $token = Str::random(40);

            $invitation = WorkspaceInvitation::create([
                'workspace_id' => $workspace->id,
                'email' => Str::lower($email),
                'role' => $role,
                'token_hash' => Hash::make($token),
                'invited_by' => $inviter->id,
                'expires_at' => now()->addDays(7),
            ]);

            $this->audit->handle(
                'workspace.member_invited',
                $invitation,
                $inviter,
                $workspace,
                metadata: ['email' => Str::lower($email), 'role' => $role->value],
            );

            return $invitation;
        });
    }
}
