<?php

namespace Database\Seeders;

use App\Actions\Projects\BuildProjectFullTextCandidates;
use App\Actions\Projects\ProjectCorpusMembershipHasher;
use App\Actions\Projects\RecordProjectScreeningDecision;
use App\Actions\Projects\RefreshProjectFullTextBatchCounts;
use App\Actions\Projects\ResolveProjectScreeningConflict;
use App\Actions\Projects\StartProjectScreeningBatch;
use App\Actions\Workspaces\CreatePersonalWorkspace;
use App\Enums\ProjectFullTextBatchStatus;
use App\Enums\ProjectFullTextItemStatus;
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
use App\Models\ProjectCorpusDedupRun;
use App\Models\ProjectFullTextBatch;
use App\Models\ProjectFullTextItem;
use App\Models\ProjectMembership;
use App\Models\ProjectProtocol;
use App\Models\ProjectProtocolVersion;
use App\Models\ProjectScreeningAssignment;
use App\Models\ProjectScreeningConflict;
use App\Models\ProjectSearchPlan;
use App\Models\ProjectSearchRun;
use App\Models\ProjectSearchRunItem;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMembership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Nexus\Screening\Domain\ScreeningDecision;
use Ramsey\Uuid\Uuid;

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

        $searchProject = $this->searchReadyProject($lab, $owner);
        $this->projectMembership($searchProject, $owner, ProjectRole::Owner);
        $this->projectMembership($searchProject, $reviewer, ProjectRole::Reviewer);
        $this->projectMembership($searchProject, $viewer, ProjectRole::Viewer);
        $this->completedProjectProtocol($searchProject, $owner);
        $this->searchPlan($searchProject, $owner);
        $this->demoCorpus($searchProject, $owner);

        $lockedProject = $this->lockedCorpusProject($lab, $owner);
        $this->projectMembership($lockedProject, $owner, ProjectRole::Owner);
        $this->projectMembership($lockedProject, $reviewer, ProjectRole::Reviewer);
        $this->projectMembership($lockedProject, $viewer, ProjectRole::Viewer);
        $this->completedProjectProtocol($lockedProject, $owner);
        $this->searchPlan($lockedProject, $owner);
        $this->demoCorpus($lockedProject, $owner, locked: true);

        $screeningProject = $this->screeningProject($lab, $owner);
        $this->projectMembership($screeningProject, $owner, ProjectRole::Owner);
        $this->projectMembership($screeningProject, $admin, ProjectRole::Adjudicator);
        $this->projectMembership($screeningProject, $reviewer, ProjectRole::Reviewer);
        $this->projectMembership($screeningProject, $viewer, ProjectRole::Viewer);
        $this->completedProjectProtocol($screeningProject, $owner);
        $this->searchPlan($screeningProject, $owner);
        $this->demoCorpus($screeningProject, $owner, locked: true);
        $this->demoScreening($screeningProject, $owner, $reviewer, $admin);

        $completedScreeningProject = $this->completedScreeningProject($lab, $owner);
        $this->projectMembership($completedScreeningProject, $owner, ProjectRole::Owner);
        $this->projectMembership($completedScreeningProject, $admin, ProjectRole::Adjudicator);
        $this->projectMembership($completedScreeningProject, $reviewer, ProjectRole::Reviewer);
        $this->projectMembership($completedScreeningProject, $viewer, ProjectRole::Viewer);
        $this->completedProjectProtocol($completedScreeningProject, $owner);
        $this->searchPlan($completedScreeningProject, $owner);
        $this->demoCorpus($completedScreeningProject, $owner, locked: true);
        $this->demoCompletedScreening($completedScreeningProject, $owner, $reviewer, $admin);

        $runningFullTextProject = $this->fullTextRunningProject($lab, $owner);
        $this->projectMembership($runningFullTextProject, $owner, ProjectRole::Owner);
        $this->projectMembership($runningFullTextProject, $admin, ProjectRole::Adjudicator);
        $this->projectMembership($runningFullTextProject, $reviewer, ProjectRole::Reviewer);
        $this->projectMembership($runningFullTextProject, $viewer, ProjectRole::Viewer);
        $this->completedProjectProtocol($runningFullTextProject, $owner);
        $this->searchPlan($runningFullTextProject, $owner);
        $this->demoCorpus($runningFullTextProject, $owner, locked: true);
        $this->demoCompletedScreening($runningFullTextProject, $owner, $reviewer, $admin);
        $this->demoFullTextBatch($runningFullTextProject, $owner, 'running');

        $completedFullTextProject = $this->fullTextCompletedProject($lab, $owner);
        $this->projectMembership($completedFullTextProject, $owner, ProjectRole::Owner);
        $this->projectMembership($completedFullTextProject, $admin, ProjectRole::Adjudicator);
        $this->projectMembership($completedFullTextProject, $reviewer, ProjectRole::Reviewer);
        $this->projectMembership($completedFullTextProject, $viewer, ProjectRole::Viewer);
        $this->completedProjectProtocol($completedFullTextProject, $owner);
        $this->searchPlan($completedFullTextProject, $owner);
        $this->demoCorpus($completedFullTextProject, $owner, locked: true);
        $this->demoCompletedScreening($completedFullTextProject, $owner, $reviewer, $admin);
        $this->demoFullTextBatch($completedFullTextProject, $owner, 'completed');

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
        $this->audit('project.created', $searchProject, $owner, $lab, 'Demo search-ready project created.', $searchProject);
        $this->audit('project.created', $lockedProject, $owner, $lab, 'Demo locked corpus project created.', $lockedProject);
        $this->audit('project.created', $screeningProject, $owner, $lab, 'Demo screening project created.', $screeningProject);
        $this->audit('project.created', $completedScreeningProject, $owner, $lab, 'Demo completed screening project created.', $completedScreeningProject);
        $this->audit('project.created', $runningFullTextProject, $owner, $lab, 'Demo running full-text project created.', $runningFullTextProject);
        $this->audit('project.created', $completedFullTextProject, $owner, $lab, 'Demo completed full-text project created.', $completedFullTextProject);
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

    private function searchReadyProject(Workspace $workspace, User $owner): Project
    {
        return Project::updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'slug' => 'cardiometabolic-review-search-ready',
            ],
            [
                'name' => 'Cardiometabolic Review Search Strategy',
                'owner_user_id' => $owner->id,
                'description' => 'Demo project with a completed protocol and editable search plan.',
                'review_type' => ReviewType::SystematicReview,
                'status' => ProjectStatus::ReadyForSearch,
                'metadata' => ['source' => 'demo-seeder'],
            ],
        );
    }

    private function lockedCorpusProject(Workspace $workspace, User $owner): Project
    {
        return Project::updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'slug' => 'cardiometabolic-review-locked-corpus',
            ],
            [
                'name' => 'Locked Cardiometabolic Evidence Snapshot',
                'owner_user_id' => $owner->id,
                'description' => 'Demo project with a locked corpus snapshot for audit-state review.',
                'review_type' => ReviewType::SystematicReview,
                'status' => ProjectStatus::LockedCorpus,
                'locked_at' => now()->subHours(6),
                'locked_by' => (string) $owner->id,
                'lock_reason' => 'Demo locked snapshot for corpus review.',
                'metadata' => ['source' => 'demo-seeder'],
            ],
        );
    }

    private function screeningProject(Workspace $workspace, User $owner): Project
    {
        return Project::updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'slug' => 'cardiometabolic-title-abstract-screening',
            ],
            [
                'name' => 'Cardiometabolic Title Abstract Screening',
                'owner_user_id' => $owner->id,
                'description' => 'Demo project with active title and abstract screening assignments.',
                'review_type' => ReviewType::SystematicReview,
                'status' => ProjectStatus::LockedCorpus,
                'locked_at' => now()->subHours(4),
                'locked_by' => (string) $owner->id,
                'lock_reason' => 'Demo locked snapshot for title and abstract screening.',
                'metadata' => ['source' => 'demo-seeder'],
            ],
        );
    }

    private function completedScreeningProject(Workspace $workspace, User $owner): Project
    {
        return Project::updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'slug' => 'cardiometabolic-screening-handoff-ready',
            ],
            [
                'name' => 'Cardiometabolic Screening Handoff',
                'owner_user_id' => $owner->id,
                'description' => 'Demo project with completed title and abstract screening outcomes.',
                'review_type' => ReviewType::SystematicReview,
                'status' => ProjectStatus::LockedCorpus,
                'locked_at' => now()->subHours(3),
                'locked_by' => (string) $owner->id,
                'lock_reason' => 'Demo locked snapshot for completed screening handoff.',
                'metadata' => ['source' => 'demo-seeder'],
            ],
        );
    }

    private function fullTextRunningProject(Workspace $workspace, User $owner): Project
    {
        return Project::updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'slug' => 'cardiometabolic-full-text-running',
            ],
            [
                'name' => 'Cardiometabolic Full-Text Retrieval',
                'owner_user_id' => $owner->id,
                'description' => 'Demo project with a running full-text retrieval batch.',
                'review_type' => ReviewType::SystematicReview,
                'status' => ProjectStatus::LockedCorpus,
                'locked_at' => now()->subHours(2),
                'locked_by' => (string) $owner->id,
                'lock_reason' => 'Demo locked snapshot for full-text retrieval.',
                'metadata' => ['source' => 'demo-seeder'],
            ],
        );
    }

    private function fullTextCompletedProject(Workspace $workspace, User $owner): Project
    {
        return Project::updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'slug' => 'cardiometabolic-full-text-audit',
            ],
            [
                'name' => 'Cardiometabolic Full-Text Audit',
                'owner_user_id' => $owner->id,
                'description' => 'Demo project with completed full-text retrieval and source audit rows.',
                'review_type' => ReviewType::SystematicReview,
                'status' => ProjectStatus::LockedCorpus,
                'locked_at' => now()->subHour(),
                'locked_by' => (string) $owner->id,
                'lock_reason' => 'Demo locked snapshot for full-text artifact audit.',
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

    private function completedProjectProtocol(Project $project, User $owner): ProjectProtocol
    {
        $protocol = ProjectProtocol::updateOrCreate(
            ['project_id' => $project->id],
            [
                'status' => ProtocolStatus::Complete,
                'version' => 2,
                'title' => $project->name,
                'research_question' => 'What digital interventions improve cardiometabolic risk among adults in primary care?',
                'background' => 'The demo lab has completed the protocol and is ready to draft provider-specific searches.',
                'inclusion_criteria' => 'Randomized and observational studies of digital cardiometabolic interventions in adult primary care populations.',
                'exclusion_criteria' => 'Editorials, pediatric-only cohorts, and studies without patient-level outcomes.',
                'target_providers' => ['openalex', 'crossref', 'pubmed'],
                'date_range_start' => '2020-01-01',
                'date_range_end' => '2026-05-29',
                'no_date_limit' => false,
                'language_policy' => 'English-language records; translate non-English abstracts manually when needed.',
                'min_reviewer_count' => 2,
                'ai_screening_policy' => 'human_only',
                'full_text_policy' => 'optional',
                'created_by' => $owner->id,
                'updated_by' => $owner->id,
                'completed_at' => now(),
            ],
        );

        ProjectProtocolVersion::updateOrCreate(
            [
                'project_protocol_id' => $protocol->id,
                'version' => $protocol->version,
            ],
            [
                'project_id' => $project->id,
                'status' => $protocol->status,
                'snapshot' => $protocol->load('project')->snapshot(),
                'reason' => 'Demo protocol completed for search planning.',
                'created_by' => $owner->id,
            ],
        );

        return $protocol;
    }

    private function searchPlan(Project $project, User $owner): ProjectSearchPlan
    {
        $plan = ProjectSearchPlan::updateOrCreate(
            ['project_id' => $project->id],
            [
                'status' => 'draft',
                'version' => 1,
                'default_providers' => ['openalex', 'crossref', 'pubmed'],
                'default_year_from' => 2020,
                'default_year_to' => 2026,
                'default_result_limit' => 75,
                'include_raw_data' => false,
                'created_by' => $owner->id,
                'updated_by' => $owner->id,
            ],
        );

        $plan->queries()
            ->whereNotIn('query_key', ['primary-search', 'implementation-search'])
            ->delete();

        $plan->queries()->updateOrCreate(
            ['query_key' => 'primary-search'],
            [
                'sort_order' => 1,
                'label' => 'Primary intervention search',
                'query' => '(digital OR mobile OR telehealth) AND cardiometabolic AND "primary care"',
                'providers' => ['openalex', 'crossref', 'pubmed'],
                'year_from' => 2020,
                'year_to' => 2026,
                'result_limit' => 75,
                'include_raw_data' => false,
            ],
        );

        $plan->queries()->updateOrCreate(
            ['query_key' => 'implementation-search'],
            [
                'sort_order' => 2,
                'label' => 'Implementation and adherence search',
                'query' => '(implementation OR adherence OR engagement) AND cardiometabolic AND digital',
                'providers' => ['openalex', 'pubmed'],
                'year_from' => 2020,
                'year_to' => 2026,
                'result_limit' => 50,
                'include_raw_data' => false,
            ],
        );

        return $plan->refresh()->load('queries');
    }

    private function demoCorpus(Project $project, User $owner, bool $locked = false): void
    {
        $slug = $project->slug;
        $now = now();
        $plan = $project->searchPlan()->with('queries')->firstOrFail();

        $this->resetDemoCorpusState($project);

        $queryIds = [
            'primary-search' => $this->demoUuid("{$slug}:search-query:primary"),
            'implementation-search' => $this->demoUuid("{$slug}:search-query:implementation"),
        ];

        $run = ProjectSearchRun::updateOrCreate(
            ['id' => $this->demoUuid("{$slug}:search-run:completed")],
            [
                'project_id' => $project->id,
                'project_search_plan_id' => $plan->id,
                'status' => 'completed',
                'plan_version' => $plan->version,
                'query_count' => 2,
                'failure_count' => 0,
                'total_raw' => 17,
                'total_unique' => 12,
                'requested_by' => $owner->id,
                'started_at' => $now->copy()->subMinutes(12),
                'completed_at' => $now->copy()->subMinutes(10),
                'metadata' => ['source' => 'demo-seeder'],
            ],
        );

        foreach ($plan->queries as $query) {
            ProjectSearchRunItem::updateOrCreate(
                [
                    'project_search_run_id' => $run->id,
                    'query_key' => $query->query_key,
                ],
                [
                    'id' => $this->demoUuid("{$slug}:search-run-item:{$query->query_key}"),
                    'project_search_plan_query_id' => $query->id,
                    'sort_order' => $query->sort_order,
                    'label' => $query->label,
                    'query' => $query->query,
                    'providers' => $query->providers,
                    'year_from' => $query->year_from,
                    'year_to' => $query->year_to,
                    'result_limit' => $query->result_limit,
                    'include_raw_data' => $query->include_raw_data,
                    'status' => 'completed',
                    'core_search_query_id' => $queryIds[$query->query_key],
                    'total_raw' => $query->query_key === 'primary-search' ? 12 : 5,
                    'total_unique' => $query->query_key === 'primary-search' ? 8 : 4,
                    'duration_ms' => $query->query_key === 'primary-search' ? 820 : 540,
                    'started_at' => $now->copy()->subMinutes(12),
                    'completed_at' => $now->copy()->subMinutes(10),
                ],
            );

            DB::table('search_queries')->updateOrInsert(
                ['id' => $queryIds[$query->query_key]],
                [
                    'project_id' => $project->id,
                    'query_text' => $query->query,
                    'from_year' => $query->year_from,
                    'to_year' => $query->year_to,
                    'max_results' => $query->result_limit,
                    'offset' => 0,
                    'include_raw_data' => $query->include_raw_data,
                    'provider_aliases' => json_encode($query->providers),
                    'cache_key' => hash('sha256', "{$project->id}:{$query->query_key}"),
                    'status' => 'completed',
                    'total_raw' => $query->query_key === 'primary-search' ? 12 : 5,
                    'total_unique' => $query->query_key === 'primary-search' ? 8 : 4,
                    'duration_ms' => $query->query_key === 'primary-search' ? 820 : 540,
                    'executed_at' => $now->copy()->subMinutes(10),
                    'metadata' => json_encode(['source' => 'demo-seeder']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $authors = $this->demoAuthors($slug);
        $works = $this->demoWorks($slug);

        foreach ($authors as $author) {
            DB::table('authors')->updateOrInsert(
                ['id' => $author['id']],
                [
                    'full_name' => $author['full_name'],
                    'normalized_name' => $author['normalized_name'],
                    'orcid' => $author['orcid'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        foreach ($works as $index => $work) {
            DB::table('scholarly_works')->updateOrInsert(
                ['id' => $work['id']],
                [
                    'title' => $work['title'],
                    'abstract' => $work['abstract'],
                    'year' => $work['year'],
                    'venue_name' => $work['venue_name'],
                    'venue_type' => 'journal',
                    'url' => "https://example.test/nexus-demo/{$work['slug']}",
                    'language' => 'en',
                    'cited_by_count' => $work['cited_by_count'],
                    'is_retracted' => $work['is_retracted'],
                    'retrieved_at' => $now->copy()->subMinutes(20),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );

            foreach ($work['identifiers'] as $identifier) {
                DB::table('work_external_ids')
                    ->where('work_id', $work['id'])
                    ->whereNotIn('namespace', collect($work['identifiers'])->pluck('namespace')->all())
                    ->delete();

                DB::table('work_external_ids')->updateOrInsert(
                    [
                        'work_id' => $work['id'],
                        'namespace' => $identifier['namespace'],
                        'value' => $identifier['value'],
                    ],
                    [
                        'id' => $this->demoUuid("{$slug}:identifier:{$work['slug']}:{$identifier['namespace']}"),
                        'is_primary' => $identifier['is_primary'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }

            foreach ($work['providers'] as $providerIndex => $provider) {
                DB::table('work_providers')->updateOrInsert(
                    [
                        'work_id' => $work['id'],
                        'provider_alias' => $provider,
                    ],
                    [
                        'id' => $this->demoUuid("{$slug}:provider:{$work['slug']}:{$provider}"),
                        'provider_work_id' => $this->providerWorkId($provider, $work['slug']),
                        'metadata' => json_encode(['source' => 'demo-seeder']),
                        'first_seen_at' => $now->copy()->subMinutes(25 - $providerIndex),
                        'last_seen_at' => $now->copy()->subMinutes(10),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );

                DB::table('query_works')->updateOrInsert(
                    [
                        'search_query_id' => $queryIds[$work['query_key']],
                        'work_id' => $work['id'],
                        'provider_alias' => $provider,
                    ],
                    [
                        'id' => $this->demoUuid("{$slug}:query-work:{$work['slug']}:{$provider}"),
                        'provider_work_id' => $this->providerWorkId($provider, $work['slug']),
                        'rank' => $index + $providerIndex + 1,
                        'seen_at' => $now->copy()->subMinutes(20 - $providerIndex),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }

            DB::table('work_authors')->updateOrInsert(
                [
                    'work_id' => $work['id'],
                    'author_id' => $authors[$index % count($authors)]['id'],
                ],
                [
                    'id' => $this->demoUuid("{$slug}:work-author:{$work['slug']}:primary"),
                    'position' => 1,
                    'is_corresponding' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $clusterId = $this->demoUuid("{$slug}:dedup-cluster:digital-coaching");
        DB::table('dedup_clusters')->updateOrInsert(
            ['id' => $clusterId],
            [
                'project_id' => $project->id,
                'strategy' => 'demo-title-doi',
                'thresholds' => json_encode(['title_similarity' => 0.92]),
                'representative_work_id' => $works[0]['id'],
                'cluster_size' => 2,
                'confidence' => 0.9400,
                'metadata' => json_encode(['source' => 'demo-seeder']),
                'is_locked' => $locked,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        foreach ([[$works[0], true], [$works[5], false]] as [$work, $representative]) {
            DB::table('cluster_members')->updateOrInsert(
                [
                    'cluster_id' => $clusterId,
                    'work_id' => $work['id'],
                ],
                [
                    'id' => $this->demoUuid("{$slug}:cluster-member:{$work['slug']}"),
                    'is_representative' => $representative,
                    'reason' => 'title_doi',
                    'confidence' => 0.9400,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $this->demoDedupRun($project, $owner, $works);

        if ($locked) {
            $this->demoCorpusSnapshot($project, $owner, $works, $queryIds);
        } else {
            $project->forceFill([
                'status' => ProjectStatus::DraftCorpus,
                'locked_at' => null,
                'locked_by' => null,
                'lock_reason' => null,
            ])->save();
        }
    }

    private function resetDemoCorpusState(Project $project): void
    {
        $this->resetDemoScreeningState($project);

        DB::table('project_search_runs')
            ->where('project_id', $project->id)
            ->delete();

        DB::table('corpus_snapshots')
            ->where('project_id', $project->id)
            ->delete();

        ProjectCorpusDedupRun::query()
            ->where('project_id', $project->id)
            ->delete();

        DB::table('dedup_clusters')
            ->where('project_id', $project->id)
            ->delete();

        DB::table('search_queries')
            ->where('project_id', $project->id)
            ->delete();
    }

    private function demoCorpusSnapshot(Project $project, User $owner, array $works, array $queryIds): void
    {
        $snapshotId = $this->demoUuid("{$project->slug}:corpus-snapshot:locked");
        $now = now();
        $snapshotWorks = collect($works)
            ->reject(fn (array $work): bool => $work['slug'] === 'digital-coaching-duplicate')
            ->values();

        DB::table('corpus_snapshots')->updateOrInsert(
            ['id' => $snapshotId],
            [
                'project_id' => $project->id,
                'locked_at' => $project->locked_at ?? $now->copy()->subHours(6),
                'work_count' => $snapshotWorks->count(),
                'created_by' => (string) $owner->id,
                'lock_reason' => 'Demo locked snapshot for corpus review.',
                'metadata' => json_encode([
                    'source' => 'demo-seeder',
                    'representative_snapshot' => true,
                    'query_ids' => array_values($queryIds),
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        foreach ($snapshotWorks as $work) {
            $sourceWorks = $work['slug'] === 'digital-coaching-risk'
                ? collect($works)->whereIn('slug', ['digital-coaching-risk', 'digital-coaching-duplicate'])->values()
                : collect([$work]);
            $providers = $sourceWorks
                ->flatMap(fn (array $sourceWork): array => $sourceWork['providers'])
                ->unique()
                ->values()
                ->all();
            $searchQueryIds = $sourceWorks
                ->map(fn (array $sourceWork): string => $queryIds[$sourceWork['query_key']])
                ->unique()
                ->values()
                ->all();

            DB::table('corpus_snapshot_works')->updateOrInsert(
                [
                    'snapshot_id' => $snapshotId,
                    'work_id' => $work['id'],
                ],
                [
                    'id' => $this->demoUuid("{$project->slug}:snapshot-work:{$work['slug']}"),
                    'search_query_ids' => json_encode($searchQueryIds),
                    'provider_aliases' => json_encode($providers),
                    'provenance' => json_encode($sourceWorks
                        ->flatMap(fn (array $sourceWork): array => collect($sourceWork['providers'])
                            ->map(fn (string $provider, int $index): array => [
                                'source_work_id' => $sourceWork['id'],
                                'search_query_id' => $queryIds[$sourceWork['query_key']],
                                'query_label' => $sourceWork['query_key'] === 'primary-search'
                                    ? 'Primary intervention search'
                                    : 'Implementation and adherence search',
                                'provider_alias' => $provider,
                                'provider_work_id' => $this->providerWorkId($provider, $sourceWork['slug']),
                                'rank' => $index + 1,
                                'seen_at' => now()->subMinutes(20 - $index)->toISOString(),
                            ])
                            ->all())
                        ->all()),
                    'included_at' => $project->locked_at ?? now()->subHours(6),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $project->forceFill([
            'status' => ProjectStatus::LockedCorpus,
            'locked_at' => $project->locked_at ?? $now->copy()->subHours(6),
            'locked_by' => (string) $owner->id,
            'lock_reason' => 'Demo locked snapshot for corpus review.',
        ])->save();
    }

    private function demoScreening(Project $project, User $owner, User $reviewer, User $adjudicator): void
    {
        $this->resetDemoScreeningState($project);

        $batch = app(StartProjectScreeningBatch::class)->handle(
            $project,
            $owner,
            [$reviewer->id, $adjudicator->id],
            2,
            'Demo title and abstract screening',
        );

        $workGroups = ProjectScreeningAssignment::query()
            ->where('batch_id', $batch->id)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('work_id')
            ->values();

        $this->recordScreeningPair(
            $workGroups->get(0, collect()),
            ScreeningDecision::INCLUDE,
            ScreeningDecision::INCLUDE,
            'Eligible primary care digital intervention.',
            'Matches the intervention and setting.',
        );

        $this->recordScreeningPair(
            $workGroups->get(1, collect()),
            ScreeningDecision::INCLUDE,
            ScreeningDecision::EXCLUDE,
            'Potentially eligible patient-facing intervention.',
            'No patient outcome is visible from the abstract.',
        );

        $this->recordScreeningPair(
            $workGroups->get(2, collect()),
            ScreeningDecision::EXCLUDE,
            ScreeningDecision::NEEDS_REVIEW,
            'Implementation-only report without outcomes.',
            'Unclear whether outcomes are reported in full text.',
        );

        $resolvedWorkId = $workGroups->get(2, collect())->first()?->work_id;
        $resolvedConflict = $resolvedWorkId
            ? ProjectScreeningConflict::query()
                ->where('batch_id', $batch->id)
                ->where('work_id', $resolvedWorkId)
                ->first()
            : null;

        if ($resolvedConflict instanceof ProjectScreeningConflict) {
            app(ResolveProjectScreeningConflict::class)->handle(
                $resolvedConflict,
                $adjudicator,
                ScreeningDecision::NEEDS_REVIEW->value,
                'Route to full text because the abstract does not settle eligibility.',
                uncertainty: ['Outcome reporting unclear'],
            );
        }
    }

    private function demoCompletedScreening(Project $project, User $owner, User $reviewer, User $adjudicator): void
    {
        $this->resetDemoScreeningState($project);

        $batch = app(StartProjectScreeningBatch::class)->handle(
            $project,
            $owner,
            [$reviewer->id, $adjudicator->id],
            2,
            'Demo completed title and abstract screening',
        );

        $workGroups = ProjectScreeningAssignment::query()
            ->where('batch_id', $batch->id)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('work_id')
            ->values();

        foreach ($workGroups as $index => $assignments) {
            [$firstDecision, $secondDecision, $firstReason, $secondReason, $resolutionDecision, $resolutionReason] = match ($index % 4) {
                0 => [
                    ScreeningDecision::INCLUDE,
                    ScreeningDecision::INCLUDE,
                    'Eligible adult primary care digital intervention.',
                    'Meets population, intervention, and outcome criteria.',
                    null,
                    null,
                ],
                1 => [
                    ScreeningDecision::NEEDS_REVIEW,
                    ScreeningDecision::NEEDS_REVIEW,
                    'Abstract does not settle outcome eligibility.',
                    'Route to full text for eligibility confirmation.',
                    null,
                    null,
                ],
                2 => [
                    ScreeningDecision::EXCLUDE,
                    ScreeningDecision::EXCLUDE,
                    'No patient-level cardiometabolic outcome.',
                    'Implementation-only record without eligible outcomes.',
                    null,
                    null,
                ],
                default => [
                    ScreeningDecision::INCLUDE,
                    ScreeningDecision::EXCLUDE,
                    'Possible eligible intervention from the abstract.',
                    'No eligible primary outcome is visible.',
                    ScreeningDecision::NEEDS_REVIEW,
                    'Resolve to full text because title and abstract are inconclusive.',
                ],
            };

            $this->recordScreeningPair(
                $assignments,
                $firstDecision,
                $secondDecision,
                $firstReason,
                $secondReason,
            );

            $workId = $assignments->first()?->work_id;
            $conflict = $workId
                ? ProjectScreeningConflict::query()
                    ->where('batch_id', $batch->id)
                    ->where('work_id', $workId)
                    ->first()
                : null;

            if ($conflict instanceof ProjectScreeningConflict && $resolutionDecision instanceof ScreeningDecision) {
                app(ResolveProjectScreeningConflict::class)->handle(
                    $conflict,
                    $adjudicator,
                    $resolutionDecision->value,
                    $resolutionReason,
                    uncertainty: ['Title and abstract are inconclusive'],
                );
            }
        }
    }

    private function demoFullTextBatch(Project $project, User $owner, string $state): void
    {
        $this->resetDemoFullTextState($project);

        $candidateSet = app(BuildProjectFullTextCandidates::class)->handle($project->refresh()->load('protocol'));
        $screeningBatch = $candidateSet['screening_batch'];
        $snapshot = $candidateSet['snapshot'];

        if (! $screeningBatch || ! $snapshot || $candidateSet['candidates'] === []) {
            return;
        }

        $now = now();
        $batch = ProjectFullTextBatch::updateOrCreate(
            ['id' => $this->demoUuid("{$project->slug}:full-text:batch:{$state}")],
            [
                'project_id' => $project->id,
                'screening_batch_id' => $screeningBatch->id,
                'snapshot_id' => (string) $snapshot->id,
                'status' => $state === 'completed'
                    ? ProjectFullTextBatchStatus::CompletedWithFailures
                    : ProjectFullTextBatchStatus::Running,
                'candidate_count' => count($candidateSet['candidates']),
                'success_count' => 0,
                'failed_count' => 0,
                'skipped_count' => 0,
                'manual_needed_count' => 0,
                'destination_folder' => "full-text/projects/{$project->id}/demo-{$state}",
                'source_policy' => [
                    'legal_open_access_only' => true,
                    'sources' => collect(config('nexus.full_text.sources', []))
                        ->reject(fn (mixed $_source, string $alias): bool => $alias === 'shadow_libraries')
                        ->map(fn (mixed $source): bool => is_array($source) ? (bool) ($source['enabled'] ?? true) : true)
                        ->all(),
                ],
                'requested_by' => $owner->id,
                'started_at' => $now->copy()->subMinutes($state === 'completed' ? 35 : 12),
                'completed_at' => $state === 'completed' ? $now->copy()->subMinutes(8) : null,
            ],
        );

        foreach ($candidateSet['candidates'] as $index => $candidate) {
            $status = $state === 'completed'
                ? [
                    ProjectFullTextItemStatus::Success,
                    ProjectFullTextItemStatus::Failed,
                    ProjectFullTextItemStatus::Skipped,
                    ProjectFullTextItemStatus::ManualNeeded,
                ][$index % 4]
                : match ($index) {
                    0 => ProjectFullTextItemStatus::Success,
                    1 => ProjectFullTextItemStatus::Running,
                    default => ProjectFullTextItemStatus::Queued,
                };
            $artifactPath = $status === ProjectFullTextItemStatus::Success
                ? "{$batch->destination_folder}/{$candidate['work_id']}_demo.pdf"
                : null;

            if ($artifactPath) {
                Storage::disk('public')->put($artifactPath, "%PDF-1.4\n% Nexus Scholar demo artifact\n");
            }

            ProjectFullTextItem::updateOrCreate(
                ['id' => $this->demoUuid("{$project->slug}:full-text:item:{$candidate['work_id']}")],
                [
                    'project_id' => $project->id,
                    'batch_id' => $batch->id,
                    'work_id' => $candidate['work_id'],
                    'screening_decision' => $candidate['screening_decision'],
                    'status' => $status,
                    'source_alias' => in_array($status, [
                        ProjectFullTextItemStatus::Success,
                        ProjectFullTextItemStatus::Failed,
                        ProjectFullTextItemStatus::Skipped,
                    ], true) ? 'demo_oa' : null,
                    'artifact_type' => $artifactPath ? 'pdf' : null,
                    'artifact_path' => $artifactPath,
                    'http_status' => $status === ProjectFullTextItemStatus::Success ? 200 : null,
                    'error_message' => match ($status) {
                        ProjectFullTextItemStatus::Failed => 'Demo source returned a non-PDF response.',
                        ProjectFullTextItemStatus::Skipped => 'No legal open-access artifact was found.',
                        ProjectFullTextItemStatus::ManualNeeded => 'Manual upload or library access review required.',
                        default => null,
                    },
                    'metadata' => [
                        'screening_decision_id' => $candidate['screening_decision_id'] ?? null,
                        'screening_reason' => $candidate['screening_reason'] ?? null,
                        'source' => 'demo-seeder',
                    ],
                    'started_at' => in_array($status, [
                        ProjectFullTextItemStatus::Success,
                        ProjectFullTextItemStatus::Failed,
                        ProjectFullTextItemStatus::Skipped,
                        ProjectFullTextItemStatus::Running,
                    ], true) ? $now->copy()->subMinutes(10 - min($index, 8)) : null,
                    'completed_at' => $status->isTerminal() ? $now->copy()->subMinutes(7 - min($index, 6)) : null,
                ],
            );

            $this->demoPdfFetch($candidate['work_id'], $status, $artifactPath, $now->copy()->subMinutes(7 - min($index, 6)));
        }

        $batch = app(RefreshProjectFullTextBatchCounts::class)->handle(
            $batch->refresh(),
            completeIfTerminal: $state === 'completed',
        );

        $this->audit('project.full_text.batch_started', $batch, $owner, $project->workspace, 'Demo full-text retrieval queued.', $project);

        if ($state === 'completed') {
            $this->audit('project.full_text.batch_completed', $batch, $owner, $project->workspace, 'Demo full-text retrieval completed with audit outcomes.', $project);
        }
    }

    private function demoPdfFetch(
        string $workId,
        ProjectFullTextItemStatus $status,
        ?string $artifactPath,
        mixed $attemptedAt,
    ): void {
        if (! in_array($status, [
            ProjectFullTextItemStatus::Success,
            ProjectFullTextItemStatus::Failed,
            ProjectFullTextItemStatus::Skipped,
        ], true)) {
            return;
        }

        DB::table('pdf_fetches')->updateOrInsert(
            [
                'id' => $this->demoUuid("pdf-fetch:{$workId}:{$status->value}"),
            ],
            [
                'work_id' => $workId,
                'source_alias' => 'demo_oa',
                'source_url' => 'https://example.test/full-text/'.$workId.'.pdf',
                'status' => match ($status) {
                    ProjectFullTextItemStatus::Success => 'success',
                    ProjectFullTextItemStatus::Failed => 'failure',
                    default => 'skipped',
                },
                'http_status' => $status === ProjectFullTextItemStatus::Success ? 200 : null,
                'file_path' => $artifactPath,
                'duration_ms' => $status === ProjectFullTextItemStatus::Success ? 42 : 18,
                'error_message' => match ($status) {
                    ProjectFullTextItemStatus::Failed => 'Demo source returned a non-PDF response.',
                    ProjectFullTextItemStatus::Skipped => 'No legal open-access artifact was found.',
                    default => null,
                },
                'metadata' => json_encode(['source' => 'demo-seeder', 'license' => 'demo-open-access']),
                'attempted_at' => $attemptedAt,
                'created_at' => $attemptedAt,
                'updated_at' => $attemptedAt,
            ],
        );
    }

    private function resetDemoScreeningState(Project $project): void
    {
        $this->resetDemoFullTextState($project);

        DB::table('project_screening_conflicts')
            ->where('project_id', $project->id)
            ->delete();

        DB::table('project_screening_assignments')
            ->where('project_id', $project->id)
            ->delete();

        DB::table('project_screening_batches')
            ->where('project_id', $project->id)
            ->delete();

        DB::table('screening_decisions')
            ->where('project_id', $project->id)
            ->delete();

        DB::table('screening_runs')
            ->where('project_id', $project->id)
            ->delete();
    }

    private function resetDemoFullTextState(Project $project): void
    {
        $workIds = DB::table('corpus_snapshot_works')
            ->join('corpus_snapshots', 'corpus_snapshots.id', '=', 'corpus_snapshot_works.snapshot_id')
            ->where('corpus_snapshots.project_id', $project->id)
            ->pluck('corpus_snapshot_works.work_id')
            ->all();

        DB::table('project_full_text_items')
            ->where('project_id', $project->id)
            ->delete();

        DB::table('project_full_text_batches')
            ->where('project_id', $project->id)
            ->delete();

        if ($workIds !== []) {
            DB::table('pdf_fetches')
                ->whereIn('work_id', $workIds)
                ->delete();
        }

        DB::table('audit_events')
            ->where('project_id', $project->id)
            ->where('event_type', 'like', 'project.full_text.%')
            ->delete();
    }

    private function recordScreeningPair(
        mixed $assignments,
        ScreeningDecision $firstDecision,
        ScreeningDecision $secondDecision,
        string $firstReason,
        string $secondReason,
    ): void {
        $first = $assignments->first();
        $second = $assignments->skip(1)->first();

        if (! $first instanceof ProjectScreeningAssignment || ! $second instanceof ProjectScreeningAssignment) {
            return;
        }

        app(RecordProjectScreeningDecision::class)->handle(
            $first,
            $first->assignedTo,
            $firstDecision->value,
            $firstReason,
        );

        app(RecordProjectScreeningDecision::class)->handle(
            $second,
            $second->assignedTo,
            $secondDecision->value,
            $secondReason,
        );
    }

    private function demoDedupRun(Project $project, User $owner, array $works): void
    {
        $now = now();
        $digest = app(ProjectCorpusMembershipHasher::class)->handle($project);
        $uniqueWorks = count($digest['unique_work_ids']) ?: count($works);

        ProjectCorpusDedupRun::updateOrCreate(
            ['id' => $this->demoUuid("{$project->slug}:dedup-run:latest")],
            [
                'project_id' => $project->id,
                'ran_by' => $owner->id,
                'status' => 'completed',
                'membership_hash' => $digest['hash'],
                'input_count' => $uniqueWorks,
                'representative_count' => $uniqueWorks - 1,
                'duplicate_cluster_count' => 1,
                'duplicate_member_count' => 1,
                'duplicates_removed' => 1,
                'duration_ms' => 12,
                'policy_stats' => ['demo_title_doi' => 1],
                'metadata' => [
                    'source' => 'demo-seeder',
                    'raw_query_links' => $digest['raw_query_links'],
                    'cluster_strategy' => 'demo-title-doi',
                ],
                'completed_at' => $now->copy()->subMinutes(8),
            ],
        );
    }

    private function demoAuthors(string $slug): array
    {
        return [
            ['id' => $this->demoUuid('author:nguyen'), 'full_name' => 'Anika Nguyen', 'normalized_name' => 'anika nguyen', 'orcid' => '0000-0002-0000-0001'],
            ['id' => $this->demoUuid('author:haddad'), 'full_name' => 'Lina Haddad', 'normalized_name' => 'lina haddad', 'orcid' => '0000-0002-0000-0002'],
            ['id' => $this->demoUuid('author:patel'), 'full_name' => 'Samir Patel', 'normalized_name' => 'samir patel', 'orcid' => '0000-0002-0000-0003'],
            ['id' => $this->demoUuid('author:moreno'), 'full_name' => 'Isabel Moreno', 'normalized_name' => 'isabel moreno', 'orcid' => '0000-0002-0000-0004'],
        ];
    }

    private function demoWorks(string $slug): array
    {
        $rows = [
            ['digital-coaching-risk', 'Digital coaching for cardiometabolic risk in primary care', 'A randomized evaluation of remote digital coaching for cardiometabolic risk management in adult primary care.', 2024, 'Primary Care Digital Health', false, ['openalex', 'crossref'], [['doi', '10.1000/nexus.001', true], ['openalex', 'W-DEMO-001', false]], 'primary-search', 42],
            ['mobile-reminders-adherence', 'Mobile reminders for cardiometabolic medication adherence', null, 2022, 'Journal of Medication Support', false, ['pubmed'], [['pubmed', '39000001', true]], 'primary-search', 18],
            ['telehealth-monitoring', 'Telehealth monitoring for adults with metabolic syndrome', 'Remote monitoring was associated with improved follow-up completion in primary care clinics.', 2021, 'Telemedicine Evidence Review', false, ['openalex'], [], 'primary-search', 27],
            ['pharmacy-blood-pressure', 'Community pharmacy blood pressure follow-up after digital referral', 'A pragmatic cohort study of pharmacy referral and blood pressure follow-up.', 2023, 'Implementation Science in Care', false, ['crossref', 'pubmed'], [['doi', '10.1000/nexus.004', true]], 'primary-search', 13],
            ['remote-lifestyle-app', 'Remote lifestyle app engagement and cardiometabolic outcomes', 'Engagement with a lifestyle application was tracked alongside cardiometabolic outcomes.', 2020, 'Digital Therapeutics Quarterly', false, ['pubmed'], [['pubmed', '39000005', true]], 'primary-search', 31],
            ['digital-coaching-duplicate', 'Digital health coaching for cardiometabolic risk in primary care', 'A near-duplicate record from another provider with overlapping title and DOI evidence.', 2024, 'Primary Care Digital Health', false, ['semantic_scholar', 'openalex'], [['doi', '10.1000/nexus.001', true], ['s2', 'S2-DEMO-006', false]], 'primary-search', 39],
            ['ai-risk-feedback', 'AI-assisted cardiometabolic risk feedback in clinics', 'Risk feedback generated by a clinical AI assistant was reviewed by primary care teams.', 2025, 'Clinical Decision Support', false, ['semantic_scholar'], [['s2', 'S2-DEMO-007', true]], 'implementation-search', 9],
            ['implementation-barriers', 'Implementation barriers for digital cardiometabolic interventions', 'Qualitative evidence about workflow barriers, staffing, and patient engagement.', 2021, 'Implementation Reports', false, ['semantic_scholar', 'crossref'], [['doi', '10.1000/nexus.008', true]], 'implementation-search', 16],
            ['sms-adherence', 'SMS adherence support for hypertension and diabetes reviews', 'SMS support was evaluated in a mixed chronic disease primary care cohort.', 2020, 'Chronic Care Informatics', false, ['pubmed', 'crossref'], [['pubmed', '39000009', true]], 'primary-search', 22],
            ['patient-portal-followup', 'Patient portal follow-up for cardiometabolic laboratory monitoring', 'Portal reminders improved laboratory monitoring completion in a multicenter cohort.', 2023, 'Ambulatory Care Informatics', false, ['openalex', 'semantic_scholar'], [['openalex', 'W-DEMO-010', true]], 'implementation-search', 25],
            ['retracted-telehealth-trial', 'Retracted telehealth intervention trial for cardiometabolic outcomes', 'This record is marked retracted in the demo corpus to test audit visibility.', 2025, 'Retracted Clinical Trials', true, ['semantic_scholar'], [['s2', 'S2-DEMO-011', true]], 'implementation-search', 2],
            ['care-manager-dashboard', 'Care manager dashboard use during cardiometabolic follow-up', 'Care managers used a dashboard to prioritize follow-up for high-risk adult patients.', 2022, 'Care Management Systems', false, ['openalex', 'pubmed'], [['doi', '10.1000/nexus.012', true], ['pubmed', '39000012', false]], 'primary-search', 34],
        ];

        return collect($rows)
            ->map(fn (array $row): array => [
                'slug' => $row[0],
                'id' => $this->demoUuid("{$slug}:work:{$row[0]}"),
                'title' => $row[1],
                'abstract' => $row[2],
                'year' => $row[3],
                'venue_name' => $row[4],
                'is_retracted' => $row[5],
                'providers' => $row[6],
                'identifiers' => collect($row[7])
                    ->map(fn (array $identifier): array => [
                        'namespace' => $identifier[0],
                        'value' => $identifier[1],
                        'is_primary' => $identifier[2],
                    ])
                    ->all(),
                'query_key' => $row[8],
                'cited_by_count' => $row[9],
            ])
            ->all();
    }

    private function providerWorkId(string $provider, string $workSlug): string
    {
        return match ($provider) {
            'doi', 'crossref' => '10.1000/nexus.'.substr(hash('crc32b', $workSlug), 0, 6),
            'pubmed' => '39'.substr(hash('crc32b', $workSlug), 0, 6),
            'semantic_scholar' => 'S2-'.strtoupper(substr(hash('crc32b', $workSlug), 0, 10)),
            default => 'W-'.strtoupper(substr(hash('crc32b', $workSlug), 0, 10)),
        };
    }

    private function demoUuid(string $key): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, 'nexus-scholar-web-demo:'.$key)->toString();
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
