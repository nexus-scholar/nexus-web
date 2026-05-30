<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Queries\Projects\ProjectDeduplicationReadModel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectCorpusDeduplicationController extends Controller
{
    public function index(
        Request $request,
        Project $project,
        ProjectDeduplicationReadModel $deduplication,
    ): Response {
        $project->load('workspace');

        $this->authorize('viewDeduplication', $project);

        return Inertia::render('projects/deduplication', [
            'project' => $this->projectPayload($project),
            'deduplication' => $deduplication->forProject(
                $project,
                $request->query('cluster') ? (string) $request->query('cluster') : null,
            ),
            'can' => [
                'view_deduplication' => $request->user()->can('viewDeduplication', $project),
                'deduplicate_corpus' => $request->user()->can('deduplicateCorpus', $project),
                'lock_corpus' => $request->user()->can('lockCorpus', $project),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function projectPayload(Project $project): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'review_type' => $project->review_type?->value,
            'review_type_label' => $project->review_type?->label(),
            'status' => $project->status->value,
            'status_label' => $project->status->label(),
            'locked_at' => $project->locked_at?->toISOString(),
            'workspace' => [
                'id' => $project->workspace->id,
                'name' => $project->workspace->name,
            ],
            'urls' => [
                'overview' => route('projects.show', $project, absolute: false),
                'search_plan' => route('projects.search-plan.edit', $project, absolute: false),
                'corpus' => route('projects.corpus.index', $project, absolute: false),
                'deduplication' => route('projects.deduplication.index', $project, absolute: false),
                'deduplicate' => route('projects.corpus.deduplicate', $project, absolute: false),
                'lock' => route('projects.corpus.lock', $project, absolute: false),
                'activity' => route('projects.activity.index', $project, absolute: false),
            ],
        ];
    }
}
