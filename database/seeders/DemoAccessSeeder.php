<?php

namespace Database\Seeders;

use App\Actions\Workspaces\CreatePersonalWorkspace;
use App\Enums\ProjectMembershipStatus;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\ProtocolStatus;
use App\Enums\ReviewType;
use App\Enums\WorkspaceMembershipStatus;
use App\Enums\WorkspaceRole;
use App\Enums\WorkspaceType;
use App\Models\AuditEvent;
use App\Models\OauthIdentity;
use App\Models\Project;
use App\Models\ProjectMembership;
use App\Models\ProjectProtocol;
use App\Models\ProjectProtocolVersion;
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

        collect([$owner, $admin, $reviewer, $viewer])
            ->each(fn (User $user) => $user->forceFill(['current_workspace_id' => $lab->id])->save());

        $demoProject = $this->project($lab, $owner);
        $this->projectMembership($demoProject, $owner, ProjectRole::Owner);
        $this->projectMembership($demoProject, $reviewer, ProjectRole::Reviewer);
        $this->projectMembership($demoProject, $viewer, ProjectRole::Viewer);
        $this->projectProtocol($demoProject, $owner);

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
        $this->audit('project.created', $demoProject, $owner, $lab, 'Demo project created.', $demoProject);
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

    private function project(Workspace $workspace, User $owner): Project
    {
        return Project::updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'slug' => 'ai-screening-primary-care-review',
            ],
            [
                'name' => 'AI Screening in Primary Care Reviews',
                'owner_user_id' => $owner->id,
                'description' => 'Demo project for protocol readiness and project role checks.',
                'review_type' => ReviewType::SystematicReview,
                'status' => ProjectStatus::Draft,
                'metadata' => ['source' => 'demo-seeder'],
            ],
        );
    }

    private function projectMembership(Project $project, User $user, ProjectRole $role): ProjectMembership
    {
        return ProjectMembership::updateOrCreate(
            [
                'project_id' => $project->id,
                'user_id' => $user->id,
            ],
            [
                'role' => $role,
                'status' => ProjectMembershipStatus::Active,
                'joined_at' => now(),
                'removed_at' => null,
            ],
        );
    }

    private function projectProtocol(Project $project, User $owner): ProjectProtocol
    {
        $protocol = ProjectProtocol::updateOrCreate(
            ['project_id' => $project->id],
            [
                'status' => ProtocolStatus::Draft,
                'version' => 1,
                'title' => $project->name,
                'research_question' => 'How accurate and efficient is AI-assisted screening in primary care evidence reviews?',
                'background' => 'The demo lab wants a traceable protocol before search starts.',
                'inclusion_criteria' => 'Peer-reviewed studies evaluating AI-assisted screening workflows.',
                'exclusion_criteria' => '',
                'target_providers' => ['openalex', 'crossref'],
                'date_range_start' => null,
                'date_range_end' => null,
                'no_date_limit' => true,
                'language_policy' => 'English-language records for the MVP demo.',
                'min_reviewer_count' => 2,
                'ai_screening_policy' => 'human_only',
                'full_text_policy' => 'optional',
                'created_by' => $owner->id,
                'updated_by' => $owner->id,
            ],
        );

        ProjectProtocolVersion::firstOrCreate(
            [
                'project_protocol_id' => $protocol->id,
                'version' => $protocol->version,
            ],
            [
                'project_id' => $project->id,
                'status' => $protocol->status,
                'snapshot' => $protocol->load('project')->snapshot(),
                'reason' => 'Demo protocol draft.',
                'created_by' => $owner->id,
            ],
        );

        return $protocol;
    }

    private function audit(
        string $eventType,
        Model $target,
        User $actor,
        ?Workspace $workspace = null,
        ?string $reason = null,
        ?Project $project = null,
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
                'project_id' => $project?->id,
                'actor_user_id' => $actor->id,
                'reason' => $reason,
                'metadata' => ['source' => 'demo-seeder'],
                'occurred_at' => now(),
            ],
        );
    }
}
