<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Queries\Projects\CorpusFilters;
use App\Queries\Projects\ProjectCorpusReadModel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectCorpusController extends Controller
{
    public function index(
        Request $request,
        Project $project,
        ProjectCorpusReadModel $corpus,
    ): Response {
        $project->load('workspace');

        $this->authorize('viewCorpus', $project);

        return Inertia::render('projects/corpus', [
            'project' => $this->projectPayload($project),
            'corpus' => $corpus->forProject($project, CorpusFilters::fromRequest($request)),
            'can' => [
                'view_corpus' => $request->user()->can('viewCorpus', $project),
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
                'protocol' => route('projects.protocol.edit', $project, absolute: false),
                'search_plan' => route('projects.search-plan.edit', $project, absolute: false),
                'corpus' => route('projects.corpus.index', $project, absolute: false),
                'activity' => route('projects.activity.index', $project, absolute: false),
            ],
        ];
    }
}
