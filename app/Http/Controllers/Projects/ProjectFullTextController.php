<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Queries\Projects\ProjectFullTextReadModel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectFullTextController extends Controller
{
    public function index(Request $request, Project $project, ProjectFullTextReadModel $fullText): Response
    {
        $project->load('workspace');

        $this->authorize('viewFullText', $project);

        return Inertia::render('projects/full-text', [
            'project' => $this->projectPayload($project),
            'fullText' => $fullText->forProject(
                $project,
                $request->user(),
                $request->query('item') ? (string) $request->query('item') : null,
            ),
            'can' => [
                'view_full_text' => $request->user()->can('viewFullText', $project),
                'manage_full_text' => $request->user()->can('manageFullText', $project),
                'download_full_text_artifact' => $request->user()->can('downloadFullTextArtifact', $project),
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
                'corpus' => route('projects.corpus.index', $project, absolute: false),
                'deduplication' => route('projects.deduplication.index', $project, absolute: false),
                'screening' => route('projects.screening.index', $project, absolute: false),
                'full_text' => route('projects.full-text.index', $project, absolute: false),
                'full_text_batches' => route('projects.full-text.batches.store', $project, absolute: false),
                'activity' => route('projects.activity.index', $project, absolute: false),
            ],
        ];
    }
}
