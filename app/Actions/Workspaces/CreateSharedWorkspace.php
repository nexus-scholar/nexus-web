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

class CreateSharedWorkspace
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    public function handle(User $owner, string $name): Workspace
    {
        return DB::transaction(function () use ($name, $owner): Workspace {
            $workspace = Workspace::create([
                'name' => $name,
                'slug' => $this->uniqueSlug($name),
                'type' => WorkspaceType::Shared,
                'owner_user_id' => $owner->id,
            ]);

            $workspace->memberships()->create([
                'user_id' => $owner->id,
                'role' => WorkspaceRole::Owner,
                'status' => WorkspaceMembershipStatus::Active,
                'joined_at' => now(),
            ]);

            $this->audit->handle('workspace.created', $workspace, $owner, $workspace, metadata: [
                'type' => WorkspaceType::Shared->value,
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
