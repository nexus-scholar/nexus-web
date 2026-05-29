<?php

namespace App\Actions\Workspaces;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\WorkspaceMembershipStatus;
use App\Enums\WorkspaceRole;
use App\Enums\WorkspaceType;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreatePersonalWorkspace
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    public function handle(User $user): Workspace
    {
        return DB::transaction(function () use ($user): Workspace {
            $workspace = Workspace::create([
                'name' => "{$user->name}'s Workspace",
                'slug' => $this->uniqueSlug($user->name.' workspace'),
                'type' => WorkspaceType::Personal,
                'owner_user_id' => $user->id,
            ]);

            $workspace->memberships()->create([
                'user_id' => $user->id,
                'role' => WorkspaceRole::Owner,
                'status' => WorkspaceMembershipStatus::Active,
                'joined_at' => now(),
            ]);

            $user->forceFill(['current_workspace_id' => $workspace->id])->save();

            $this->audit->handle('workspace.created', $workspace, $user, $workspace, metadata: [
                'type' => WorkspaceType::Personal->value,
            ]);

            return $workspace;
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';
        $slug = $base;
        $counter = 2;

        while (Workspace::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
