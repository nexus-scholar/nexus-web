<?php

namespace Database\Seeders;

use App\Actions\Workspaces\CreatePersonalWorkspace;
use App\Enums\WorkspaceMembershipStatus;
use App\Enums\WorkspaceRole;
use App\Enums\WorkspaceType;
use App\Models\AuditEvent;
use App\Models\OauthIdentity;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMembership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoAccessSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'password';

    public function run(): void
    {
        $operator = $this->user('Nexus Operator', 'operator@nexusscholar.test', operator: true);
        $owner = $this->user('Dr. Lina Haddad', 'owner@nexusscholar.test');
        $admin = $this->user('Dr. Samir Patel', 'admin@nexusscholar.test');
        $reviewer = $this->user('Maya Reviewer', 'reviewer@nexusscholar.test');
        $viewer = $this->user('Victor Viewer', 'viewer@nexusscholar.test');
        $disabled = $this->user('Disabled Researcher', 'disabled@nexusscholar.test', disabledBy: $operator);

        collect([$operator, $owner, $admin, $reviewer, $viewer, $disabled])
            ->each(fn (User $user) => $this->personalWorkspace($user));

        $lab = $this->workspace('Evidence Synthesis Lab', 'evidence-synthesis-lab', $owner);
        $this->membership($lab, $owner, WorkspaceRole::Owner);
        $this->membership($lab, $admin, WorkspaceRole::Admin);
        $this->membership($lab, $reviewer, WorkspaceRole::Member);
        $this->membership($lab, $viewer, WorkspaceRole::Member);

        $suspended = $this->workspace('Suspended Review Group', 'suspended-review-group', $owner);
        $suspended->forceFill([
            'suspended_at' => $suspended->suspended_at ?? now(),
            'suspended_by' => $operator->id,
            'suspended_reason' => 'Demo suspended workspace for operator review.',
        ])->save();

        $this->membership($suspended, $owner, WorkspaceRole::Owner);

        WorkspaceInvitation::updateOrCreate(
            [
                'workspace_id' => $lab->id,
                'email' => 'pending-reviewer@nexusscholar.test',
            ],
            [
                'role' => WorkspaceRole::Member,
                'token_hash' => Hash::make('demo-invitation-token'),
                'invited_by' => $owner->id,
                'accepted_by' => null,
                'accepted_at' => null,
                'revoked_at' => null,
                'expires_at' => now()->addDays(7),
            ],
        );

        OauthIdentity::updateOrCreate(
            [
                'provider' => 'google',
                'provider_user_id' => 'demo-owner-google',
            ],
            [
                'user_id' => $owner->id,
                'email' => $owner->email,
                'email_verified_at' => now(),
                'last_login_at' => now(),
            ],
        );

        $this->audit('user.disabled', $disabled, $operator, null, 'Demo disabled account.');
        $this->audit('workspace.suspended', $suspended, $operator, $suspended, 'Demo suspended workspace.');
    }

    private function user(
        string $name,
        string $email,
        bool $operator = false,
        ?User $disabledBy = null,
    ): User {
        return User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make(self::DEMO_PASSWORD),
                'email_verified_at' => now(),
                'is_operator' => $operator,
                'disabled_at' => $disabledBy ? now() : null,
                'disabled_by' => $disabledBy?->id,
                'disabled_reason' => $disabledBy ? 'Demo disabled account.' : null,
            ],
        );
    }

    private function personalWorkspace(User $user): Workspace
    {
        $workspace = Workspace::query()
            ->where('owner_user_id', $user->id)
            ->where('type', WorkspaceType::Personal->value)
            ->first();

        if ($workspace instanceof Workspace) {
            $this->membership($workspace, $user, WorkspaceRole::Owner);

            if ($user->current_workspace_id === null) {
                $user->forceFill(['current_workspace_id' => $workspace->id])->save();
            }

            return $workspace;
        }

        return app(CreatePersonalWorkspace::class)->handle($user);
    }

    private function workspace(string $name, string $slug, User $owner): Workspace
    {
        return Workspace::updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'type' => WorkspaceType::Shared,
                'owner_user_id' => $owner->id,
            ],
        );
    }

    private function membership(Workspace $workspace, User $user, WorkspaceRole $role): WorkspaceMembership
    {
        return WorkspaceMembership::updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
            ],
            [
                'role' => $role,
                'status' => WorkspaceMembershipStatus::Active,
                'joined_at' => now(),
                'removed_at' => null,
            ],
        );
    }

    private function audit(
        string $eventType,
        Model $target,
        User $actor,
        ?Workspace $workspace = null,
        ?string $reason = null,
    ): void {
        AuditEvent::firstOrCreate(
            [
                'event_type' => $eventType,
                'target_type' => $target::class,
                'target_id' => (string) $target->getKey(),
            ],
            [
                'id' => (string) Str::uuid(),
                'workspace_id' => $workspace?->id,
                'actor_user_id' => $actor->id,
                'reason' => $reason,
                'metadata' => ['source' => 'demo-seeder'],
                'occurred_at' => now(),
            ],
        );
    }
}
