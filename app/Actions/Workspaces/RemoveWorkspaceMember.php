<?php

namespace App\Actions\Workspaces;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\WorkspaceMembershipStatus;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class RemoveWorkspaceMember
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    public function handle(Workspace $workspace, User $target, User $actor): WorkspaceMembership
    {
        $membership = $workspace->activeMembershipFor($target);

        if (! $membership || $membership->isOwner()) {
            throw new AuthorizationException('This workspace member cannot be removed.');
        }

        return DB::transaction(function () use ($actor, $membership, $target, $workspace): WorkspaceMembership {
            $membership->forceFill([
                'status' => WorkspaceMembershipStatus::Removed,
                'removed_at' => now(),
            ])->save();

            if ($target->current_workspace_id === $workspace->id) {
                $nextWorkspaceId = $target->activeWorkspaceMemberships()
                    ->where('workspace_id', '!=', $workspace->id)
                    ->oldest()
                    ->value('workspace_id');

                $target->forceFill(['current_workspace_id' => $nextWorkspaceId])->save();
            }

            $this->audit->handle(
                'workspace.member_removed',
                $membership,
                $actor,
                $workspace,
                metadata: ['removed_user_id' => $target->id],
            );

            return $membership;
        });
    }
}
