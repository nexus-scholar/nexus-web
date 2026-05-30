<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Queries\Projects\ProjectScreeningQueueReadModel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectScreeningQueueController extends Controller
{
    public function index(
        Request $request,
        Project $project,
        ProjectScreeningQueueReadModel $queue,
    ): Response {
        $project->load('workspace');

        $this->authorize('screenAssignedWork', $project);

        return Inertia::render('projects/screening-queue', [
            'project' => $this->projectPayload($project),
            'queue' => $queue->forReviewer(
                $project,
                $request->user(),
                $request->query('assignment') ? (string) $request->query('assignment') : null,
            ),
            'can' => [
                'screen_assigned_work' => $request->user()->can('screenAssignedWork', $project),
                'view_screening' => $request->user()->can('viewScreening', $project),
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
                'screening' => route('projects.screening.index', $project, absolute: false),
                'screening_queue' => route('projects.screening.queue', $project, absolute: false),
            ],
        ];
    }
}
