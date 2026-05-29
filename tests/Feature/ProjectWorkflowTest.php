<?php

namespace Tests\Feature;

use App\Actions\Projects\CreateProject;
use App\Actions\Workspaces\CreatePersonalWorkspace;
use App\Actions\Workspaces\CreateSharedWorkspace;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\ProtocolStatus;
use App\Enums\ReviewType;
use App\Enums\WorkspaceMembershipStatus;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_user_can_create_a_project_in_personal_workspace(): void
    {
        $user = User::factory()->create();
        app(CreatePersonalWorkspace::class)->handle($user);

        $response = $this->actingAs($user)->post(route('projects.store'), [
            'name' => 'AI screening for primary care reviews',
            'review_type' => ReviewType::SystematicReview->value,
            'research_question' => 'How accurate is AI-assisted title and abstract screening?',
            'background' => 'The lab needs a defensible screening protocol.',
        ]);

        $project = Project::query()->firstOrFail();

        $response->assertRedirect(route('projects.show', $project, absolute: false));
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'workspace_id' => $user->current_workspace_id,
            'owner_user_id' => $user->id,
            'review_type' => ReviewType::SystematicReview->value,
            'status' => ProjectStatus::Draft->value,
        ]);
        $this->assertDatabaseHas('project_memberships', [
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => ProjectRole::Owner->value,
        ]);
        $this->assertDatabaseHas('project_protocols', [
            'project_id' => $project->id,
            'title' => 'AI screening for primary care reviews',
            'status' => ProtocolStatus::Draft->value,
        ]);
        $this->assertDatabaseHas('project_protocol_versions', [
            'project_id' => $project->id,
            'version' => 1,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'project_id' => $project->id,
            'event_type' => 'project.created',
        ]);
    }

    public function test_unverified_user_cannot_create_project(): void
    {
        $user = User::factory()->unverified()->create();
        app(CreatePersonalWorkspace::class)->handle($user);

        $this->actingAs($user)
            ->post(route('projects.store'), [
                'name' => 'Blocked project',
                'review_type' => ReviewType::SystematicReview->value,
            ])
            ->assertRedirect(route('verification.notice', absolute: false));

        $this->assertDatabaseMissing('projects', [
            'name' => 'Blocked project',
        ]);
    }

    public function test_workspace_member_cannot_create_project(): void
    {
        [$workspace, $owner] = $this->sharedWorkspace();
        $member = User::factory()->create();
        app(CreatePersonalWorkspace::class)->handle($member);
        $this->workspaceMember($workspace, $member, WorkspaceRole::Member);
        $member->forceFill(['current_workspace_id' => $workspace->id])->save();

        $this->actingAs($member)
            ->post(route('projects.store'), [
                'name' => 'Unauthorized project',
                'review_type' => ReviewType::SystematicReview->value,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('projects', [
            'name' => 'Unauthorized project',
            'owner_user_id' => $owner->id,
        ]);
    }

    public function test_workspace_admin_can_create_project_without_auto_project_membership(): void
    {
        [$workspace] = $this->sharedWorkspace();
        $admin = User::factory()->create();
        app(CreatePersonalWorkspace::class)->handle($admin);
        $this->workspaceMember($workspace, $admin, WorkspaceRole::Admin);
        $admin->forceFill(['current_workspace_id' => $workspace->id])->save();

        $this->actingAs($admin)
            ->post(route('projects.store'), [
                'name' => 'Nutrition evidence map',
                'review_type' => ReviewType::EvidenceMap->value,
            ])
            ->assertRedirect();

        $project = Project::query()->firstOrFail();

        $this->assertDatabaseHas('project_memberships', [
            'project_id' => $project->id,
            'user_id' => $admin->id,
            'role' => ProjectRole::Owner->value,
        ]);
        $this->assertSame($workspace->id, $project->workspace_id);
    }

    public function test_dashboard_lists_visible_workspace_projects(): void
    {
        $user = User::factory()->create();
        $workspace = app(CreatePersonalWorkspace::class)->handle($user);
        app(CreateProject::class)->handle($workspace, $user, 'Visible review', ReviewType::SystematicReview);

        $this->actingAs($user)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('dashboard'))
            ->assertJsonPath('component', 'dashboard')
            ->assertJsonPath('props.projects.0.name', 'Visible review')
            ->assertJsonPath('props.can.create_project', true);
    }

    public function test_project_owner_can_complete_required_protocol_fields(): void
    {
        $user = User::factory()->create();
        $workspace = app(CreatePersonalWorkspace::class)->handle($user);
        $project = app(CreateProject::class)->handle($workspace, $user, 'Screening automation review', ReviewType::SystematicReview);

        $this->actingAs($user)
            ->patch(route('projects.protocol.update', $project), $this->completeProtocolPayload([
                'title' => 'Screening automation review',
                'intent' => 'complete',
            ]))
            ->assertRedirect();

        $this->assertSame(ProjectStatus::ReadyForSearch, $project->fresh()->status);
        $this->assertSame(ProtocolStatus::Complete, $project->protocol()->firstOrFail()->status);
        $this->assertDatabaseHas('project_protocol_versions', [
            'project_id' => $project->id,
            'version' => 2,
            'status' => ProtocolStatus::Complete->value,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'project_id' => $project->id,
            'event_type' => 'project.protocol.completed',
        ]);
    }

    public function test_project_protocol_provider_aliases_are_normalized_and_limited(): void
    {
        $user = User::factory()->create();
        $workspace = app(CreatePersonalWorkspace::class)->handle($user);
        $project = app(CreateProject::class)->handle($workspace, $user, 'Provider boundary review', ReviewType::SystematicReview);

        $this->actingAs($user)
            ->from(route('projects.protocol.edit', $project))
            ->patch(route('projects.protocol.update', $project), $this->completeProtocolPayload([
                'target_providers' => 'openalex, semantic-scholar, PUBMED, openalex',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(
            ['openalex', 'semantic_scholar', 'pubmed'],
            $project->protocol()->firstOrFail()->fresh()->target_providers,
        );

        $this->actingAs($user)
            ->from(route('projects.protocol.edit', $project))
            ->patch(route('projects.protocol.update', $project), $this->completeProtocolPayload([
                'target_providers' => 'openalex, unknown-provider',
            ]))
            ->assertRedirect(route('projects.protocol.edit', $project, absolute: false))
            ->assertSessionHasErrors('target_providers');
    }

    public function test_reviewer_cannot_edit_project_protocol(): void
    {
        [$workspace, $owner] = $this->sharedWorkspace();
        $project = app(CreateProject::class)->handle($workspace, $owner, 'Reviewer boundary review', ReviewType::SystematicReview);
        $reviewer = User::factory()->create();
        app(CreatePersonalWorkspace::class)->handle($reviewer);
        $this->workspaceMember($workspace, $reviewer, WorkspaceRole::Member);
        $project->memberships()->create([
            'user_id' => $reviewer->id,
            'role' => ProjectRole::Reviewer,
            'status' => 'active',
            'joined_at' => now(),
        ]);
        $reviewer->forceFill(['current_workspace_id' => $workspace->id])->save();

        $this->actingAs($reviewer)
            ->patch(route('projects.protocol.update', $project), $this->completeProtocolPayload())
            ->assertForbidden();
    }

    public function test_workspace_admin_can_view_project_without_project_membership(): void
    {
        [$workspace, $owner] = $this->sharedWorkspace();
        $project = app(CreateProject::class)->handle($workspace, $owner, 'Admin visible review', ReviewType::SystematicReview);
        $admin = User::factory()->create();
        app(CreatePersonalWorkspace::class)->handle($admin);
        $this->workspaceMember($workspace, $admin, WorkspaceRole::Admin);
        $admin->forceFill(['current_workspace_id' => $workspace->id])->save();

        $this->actingAs($admin)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('projects.show', $project))
            ->assertJsonPath('component', 'projects/show')
            ->assertJsonPath('props.project.name', 'Admin visible review')
            ->assertJsonPath('props.project.role', null)
            ->assertJsonPath('props.can.update_protocol', true);
    }

    public function test_locked_protocol_edit_requires_audit_reason_and_records_version(): void
    {
        $user = User::factory()->create();
        $workspace = app(CreatePersonalWorkspace::class)->handle($user);
        $project = app(CreateProject::class)->handle($workspace, $user, 'Locked protocol review', ReviewType::SystematicReview);
        $project->forceFill([
            'status' => ProjectStatus::Locked,
            'locked_at' => now(),
            'locked_by' => (string) $user->id,
            'lock_reason' => 'Test lock.',
        ])->save();

        $this->actingAs($user)
            ->from(route('projects.protocol.edit', $project))
            ->patch(route('projects.protocol.update', $project), $this->completeProtocolPayload([
                'title' => 'Locked protocol review amended',
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('audit_reason');

        $this->actingAs($user)
            ->from(route('projects.protocol.edit', $project))
            ->patch(route('projects.protocol.update', $project), $this->completeProtocolPayload([
                'title' => 'Locked protocol review amended',
                'audit_reason' => 'Corrected the review question before screening.',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $protocol = $project->protocol()->firstOrFail();

        $this->assertSame('Locked protocol review amended', $project->fresh()->name);
        $this->assertSame(ProtocolStatus::Amended, $protocol->fresh()->status);
        $this->assertSame(2, $protocol->fresh()->version);
        $this->assertDatabaseHas('project_protocol_versions', [
            'project_id' => $project->id,
            'version' => 2,
            'reason' => 'Corrected the review question before screening.',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'project_id' => $project->id,
            'event_type' => 'project.protocol.amended',
            'reason' => 'Corrected the review question before screening.',
        ]);
    }

    public function test_personal_workspace_project_limit_is_enforced(): void
    {
        config(['nexus.projects.personal_workspace_active_limit' => 1]);

        $user = User::factory()->create();
        $workspace = app(CreatePersonalWorkspace::class)->handle($user);
        app(CreateProject::class)->handle($workspace, $user, 'Existing review', ReviewType::SystematicReview);

        $this->actingAs($user)
            ->from(route('projects.create'))
            ->post(route('projects.store'), [
                'name' => 'Over limit review',
                'review_type' => ReviewType::SystematicReview->value,
            ])
            ->assertRedirect(route('projects.create', absolute: false))
            ->assertSessionHasErrors('name');
    }

    /**
     * @return array{0: Workspace, 1: User}
     */
    private function sharedWorkspace(): array
    {
        $owner = User::factory()->create();
        app(CreatePersonalWorkspace::class)->handle($owner);
        $workspace = app(CreateSharedWorkspace::class)->handle($owner, 'Evidence Synthesis Lab');
        $owner->forceFill(['current_workspace_id' => $workspace->id])->save();

        return [$workspace, $owner];
    }

    private function workspaceMember(Workspace $workspace, User $user, WorkspaceRole $role): void
    {
        $workspace->memberships()->create([
            'user_id' => $user->id,
            'role' => $role,
            'status' => WorkspaceMembershipStatus::Active,
            'joined_at' => now(),
        ]);
    }

    private function completeProtocolPayload(array $overrides = []): array
    {
        return [
            'intent' => 'save',
            'title' => 'Complete protocol',
            'review_type' => ReviewType::SystematicReview->value,
            'research_question' => 'What evidence supports this intervention?',
            'background' => 'The team needs a defensible protocol before search.',
            'inclusion_criteria' => 'Peer-reviewed empirical studies.',
            'exclusion_criteria' => 'Editorials and non-human studies.',
            'target_providers' => 'openalex, crossref',
            'date_range_start' => null,
            'date_range_end' => null,
            'no_date_limit' => true,
            'language_policy' => 'English-language records.',
            'min_reviewer_count' => 2,
            'ai_screening_policy' => 'human_only',
            'full_text_policy' => 'optional',
            ...$overrides,
        ];
    }

    private function inertiaHeaders(): array
    {
        $manifest = public_path('build/manifest.json');

        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => file_exists($manifest) ? hash_file('xxh128', $manifest) : '',
        ];
    }
}
