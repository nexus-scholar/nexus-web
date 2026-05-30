<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Queries\Projects\ProjectFullTextScreeningQueueReadModel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectFullTextScreeningQueueController extends Controller
{
    public function index(
        Request $request,
        Project $project,
        ProjectFullTextScreeningQueueReadModel $queue,
    ): Response {
        $project->load('workspace');

        $this->authorize('screenAssignedFullText', $project);

        return Inertia::render('projects/full-text-screening-queue', [
            'project' => $this->projectPayload($project),
            'queue' => $queue->forReviewer(
                $project,
                $request->user(),
                $request->query('assignment') ? (string) $request->query('assignment') : null,
            ),
            'can' => [
                'screen_assigned_full_text' => $request->user()->can('screenAssignedFullText', $project),
                'view_full_text_screening' => $request->user()->can('viewFullTextScreening', $project),
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
            'review_type_label' => $project->review_type?->label(),
            'status' => $project->status->value,
            'status_label' => $project->status->label(),
            'workspace' => [
                'id' => $project->workspace->id,
                'name' => $project->workspace->name,
            ],
            'urls' => [
                'overview' => route('projects.show', $project, absolute: false),
                'full_text' => route('projects.full-text.index', $project, absolute: false),
                'full_text_screening' => route('projects.full-text-screening.index', $project, absolute: false),
                'full_text_screening_queue' => route('projects.full-text-screening.queue', $project, absolute: false),
            ],
        ];
    }
}
