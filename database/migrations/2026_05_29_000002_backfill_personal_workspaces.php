<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNull('current_workspace_id')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->each(function (object $user): void {
                $workspaceId = (string) Str::uuid();
                $slug = $this->uniqueSlug($user->name.' workspace');
                $now = now();

                DB::table('workspaces')->insert([
                    'id' => $workspaceId,
                    'name' => "{$user->name}'s Workspace",
                    'slug' => $slug,
                    'type' => 'personal',
                    'owner_user_id' => $user->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('workspace_memberships')->insert([
                    'workspace_id' => $workspaceId,
                    'user_id' => $user->id,
                    'role' => 'owner',
                    'status' => 'active',
                    'joined_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('audit_events')->insert([
                    'id' => (string) Str::uuid(),
                    'workspace_id' => $workspaceId,
                    'actor_user_id' => $user->id,
                    'event_type' => 'workspace.created',
                    'target_type' => 'App\\Models\\Workspace',
                    'target_id' => $workspaceId,
                    'metadata' => json_encode(['type' => 'personal', 'source' => 'migration_backfill']),
                    'occurred_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['current_workspace_id' => $workspaceId, 'updated_at' => $now]);
            });
    }

    public function down(): void
    {
        //
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';
        $slug = $base;
        $counter = 2;

        while (DB::table('workspaces')->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
};
