<?php

namespace App\Http\Controllers\Projects;

use App\Actions\Projects\CreateProject;
use App\Enums\ReviewType;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectProtocol;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function create(Request $request): Response
    {
        $workspace = $request->user()->currentWorkspace;

        abort_if(! $workspace, 404);
        $this->authorize('createProject', $workspace);

        return Inertia::render('projects/create', [
            'selectedWorkspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'type' => $workspace->type->value,
            ],
            'reviewTypes' => $this->reviewTypeOptions(),
            'personalWorkspaceProjectLimit' => (int) config('nexus.projects.personal_workspace_active_limit', 2),
        ]);
    }

    public function store(Request $request, CreateProject $createProject): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        abort_if(! $workspace, 404);
        $this->authorize('createProject', $workspace);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:180'],
            'review_type' => ['required', Rule::enum(ReviewType::class)],
            'research_question' => ['nullable', 'string', 'max:5000'],
            'background' => ['nullable', 'string', 'max:10000'],
        ]);

        $project = $createProject->handle(
            $workspace,
            $request->user(),
            $data['name'],
            ReviewType::from($data['review_type']),
            $data['research_question'] ?? null,
            $data['background'] ?? null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project created.')]);

        return to_route('projects.show', $project);
    }

    public function show(Request $request, Project $project): Response
    {
        $project->load(['workspace', 'owner', 'protocol', 'activeMemberships.user']);

        $this->authorize('view', $project);

        return Inertia::render('projects/show', [
            'project' => $this->projectPayload($project, $request),
            'protocolReadiness' => $this->protocolReadiness($project->protocol),
            'members' => $project->activeMemberships
                ->map(fn ($membership): array => [
                    'id' => $membership->id,
                    'role' => $membership->role->value,
                    'role_label' => $membership->role->label(),
                    'user' => [
                        'id' => $membership->user->id,
                        'name' => $membership->user->name,
                        'email' => $membership->user->email,
                    ],
                ])
                ->values(),
            'can' => [
                'update_protocol' => $request->user()->can('updateProtocol', $project),
                'complete_protocol' => $request->user()->can('completeProtocol', $project),
                'view_search_plan' => $request->user()->can('viewSearchPlan', $project),
                'update_search_plan' => $request->user()->can('updateSearchPlan', $project),
                'run_search' => $request->user()->can('runSearch', $project),
                'view_corpus' => $request->user()->can('viewCorpus', $project),
                'view_deduplication' => $request->user()->can('viewDeduplication', $project),
                'view_activity' => $request->user()->can('viewActivity', $project),
            ],
        ]);
    }

    private function projectPayload(Project $project, Request $request): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'slug' => $project->slug,
            'description' => $project->description,
            'review_type' => $project->review_type?->value,
            'review_type_label' => $project->review_type?->label(),
            'status' => $project->status->value,
            'status_label' => $project->status->label(),
            'locked_at' => $project->locked_at?->toISOString(),
            'workspace' => [
                'id' => $project->workspace->id,
                'name' => $project->workspace->name,
                'type' => $project->workspace->type->value,
            ],
            'role' => $request->user()->projectRole($project)?->value,
            'role_label' => $request->user()->projectRole($project)?->label(),
            'urls' => [
                'overview' => route('projects.show', $project, absolute: false),
                'protocol' => route('projects.protocol.edit', $project, absolute: false),
                'search_plan' => route('projects.search-plan.edit', $project, absolute: false),
                'search_runs' => route('projects.search-runs.store', $project, absolute: false),
                'corpus' => route('projects.corpus.index', $project, absolute: false),
                'deduplication' => route('projects.deduplication.index', $project, absolute: false),
                'activity' => route('projects.activity.index', $project, absolute: false),
            ],
            'corpus' => $this->corpusSummary($project),
            'protocol' => $project->protocol ? [
                'id' => $project->protocol->id,
                'status' => $project->protocol->status->value,
                'status_label' => $project->protocol->status->label(),
                'version' => $project->protocol->version,
                'completed_at' => $project->protocol->completed_at?->toISOString(),
            ] : null,
        ];
    }

    private function corpusSummary(Project $project): array
    {
        $latestSnapshot = $project->isLocked()
            ? DB::table('corpus_snapshots')
                ->where('project_id', $project->id)
                ->orderByDesc('locked_at')
                ->orderByDesc('created_at')
                ->first()
            : null;

        if ($latestSnapshot) {
            return [
                'available' => (int) $latestSnapshot->work_count > 0,
                'source' => 'locked',
                'unique_works' => (int) $latestSnapshot->work_count,
                'raw_query_links' => DB::table('corpus_snapshot_works')
                    ->where('snapshot_id', $latestSnapshot->id)
                    ->count(),
            ];
        }

        $membership = DB::table('query_works')
            ->join('search_queries', 'search_queries.id', '=', 'query_works.search_query_id')
            ->where('search_queries.project_id', $project->id);

        $uniqueWorks = (clone $membership)->distinct()->count('query_works.work_id');

        return [
            'available' => $uniqueWorks > 0,
            'source' => 'draft',
            'unique_works' => $uniqueWorks,
            'raw_query_links' => (clone $membership)->count(),
        ];
    }

    private function protocolReadiness(?ProjectProtocol $protocol): array
    {
        if (! $protocol) {
            return [];
        }

        $missing = collect($protocol->readinessMissingFields());

        return collect([
            ['id' => 'title', 'label' => 'Title', 'description' => 'Project title and route identity.', 'complete' => filled($protocol->title)],
            ['id' => 'review_type', 'label' => 'Review type', 'description' => 'Systematic, scoping, thesis, living, or evidence map.', 'complete' => filled($protocol->project?->review_type?->value)],
            ['id' => 'research_question', 'label' => 'Research question', 'description' => 'The question the review is designed to answer.', 'complete' => filled($protocol->research_question)],
            ['id' => 'background', 'label' => 'Background and rationale', 'description' => 'Why the review is needed.', 'complete' => filled($protocol->background)],
            ['id' => 'inclusion_criteria', 'label' => 'Inclusion criteria', 'description' => 'Records that should enter the corpus.', 'complete' => filled($protocol->inclusion_criteria)],
            ['id' => 'exclusion_criteria', 'label' => 'Exclusion criteria', 'description' => 'Records that should stay out of the corpus.', 'complete' => filled($protocol->exclusion_criteria)],
            ['id' => 'target_providers', 'label' => 'Target providers', 'description' => 'Search providers planned for the first query run.', 'complete' => ! $missing->contains('target_providers')],
            ['id' => 'date_range', 'label' => 'Date range policy', 'description' => 'Either a date range or an explicit no-limit policy.', 'complete' => ! $missing->contains('date_range')],
            ['id' => 'language_policy', 'label' => 'Language policy', 'description' => 'Language restrictions or inclusion policy.', 'complete' => filled($protocol->language_policy)],
            ['id' => 'min_reviewer_count', 'label' => 'Reviewer count', 'description' => 'Minimum independent reviewers required.', 'complete' => $protocol->min_reviewer_count >= 1],
            ['id' => 'ai_screening_policy', 'label' => 'AI screening policy', 'description' => 'How AI assistance may be used.', 'complete' => filled($protocol->ai_screening_policy)],
            ['id' => 'full_text_policy', 'label' => 'Full-text policy', 'description' => 'How full-text retrieval and uploads are handled.', 'complete' => filled($protocol->full_text_policy)],
        ])->all();
    }

    private function reviewTypeOptions(): array
    {
        return collect(ReviewType::cases())
            ->map(fn (ReviewType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ])
            ->all();
    }
}
