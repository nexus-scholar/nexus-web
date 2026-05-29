<?php

namespace App\Actions\Projects;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\ProjectMembershipStatus;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\ProtocolStatus;
use App\Enums\ReviewType;
use App\Enums\WorkspaceType;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateProject
{
    public function __construct(
        private readonly RecordAuditEvent $audit,
        private readonly RecordProjectProtocolVersion $recordVersion,
    ) {}

    public function handle(
        Workspace $workspace,
        User $owner,
        string $name,
        ReviewType $reviewType,
        ?string $researchQuestion = null,
        ?string $background = null,
    ): Project {
        $this->ensurePersonalWorkspaceLimit($workspace);

        return DB::transaction(function () use ($background, $name, $owner, $researchQuestion, $reviewType, $workspace): Project {
            $project = Project::create([
                'workspace_id' => $workspace->id,
                'owner_user_id' => $owner->id,
                'name' => $name,
                'slug' => $this->uniqueSlug($workspace, $name),
                'review_type' => $reviewType,
                'status' => ProjectStatus::Draft,
                'metadata' => [],
            ]);

            $project->memberships()->create([
                'user_id' => $owner->id,
                'role' => ProjectRole::Owner,
                'status' => ProjectMembershipStatus::Active,
                'joined_at' => now(),
            ]);

            $protocol = $project->protocol()->create([
                'status' => ProtocolStatus::Draft,
                'version' => 1,
                'title' => $name,
                'research_question' => $researchQuestion,
                'background' => $background,
                'target_providers' => [],
                'min_reviewer_count' => 2,
                'ai_screening_policy' => 'human_only',
                'full_text_policy' => 'optional',
                'created_by' => $owner->id,
                'updated_by' => $owner->id,
            ]);

            $this->recordVersion->handle($protocol, $owner, 'Project protocol draft created.');

            $this->audit->handle(
                'project.created',
                $project,
                $owner,
                $workspace,
                metadata: [
                    'review_type' => $reviewType->value,
                ],
            );

            return $project->load(['workspace', 'owner', 'protocol']);
        });
    }

    private function ensurePersonalWorkspaceLimit(Workspace $workspace): void
    {
        if ($workspace->type !== WorkspaceType::Personal) {
            return;
        }

        $limit = (int) config('nexus.projects.personal_workspace_active_limit', 2);

        if ($workspace->activeProjects()->count() < $limit) {
            return;
        }

        throw ValidationException::withMessages([
            'name' => __('Personal workspaces can have up to :limit active projects during the MVP.', [
                'limit' => $limit,
            ]),
        ]);
    }

    private function uniqueSlug(Workspace $workspace, string $name): string
    {
        $base = Str::slug($name) ?: 'project';
        $slug = $base;
        $counter = 2;

        while ($workspace->projects()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
