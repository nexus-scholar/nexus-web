<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Queries\Projects\ProjectScreeningReadModel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectScreeningController extends Controller
{
    public function index(
        Request $request,
        Project $project,
        ProjectScreeningReadModel $screening,
    ): Response {
        $project->load('workspace');

        $this->authorize('viewScreening', $project);

        return Inertia::render('projects/screening', [
            'project' => $this->projectPayload($project),
            'screening' => $screening->forProject(
                $project,
                $request->user(),
                $request->query('conflict') ? (string) $request->query('conflict') : null,
            ),
            'can' => [
                'view_screening' => $request->user()->can('viewScreening', $project),
                'manage_screening' => $request->user()->can('manageScreening', $project),
                'screen_assigned_work' => $request->user()->can('screenAssignedWork', $project),
                'resolve_screening_conflict' => $request->user()->can('resolveScreeningConflict', $project),
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
                'corpus' => route('projects.corpus.index', $project, absolute: false),
                'deduplication' => route('projects.deduplication.index', $project, absolute: false),
                'screening' => route('projects.screening.index', $project, absolute: false),
                'screening_queue' => route('projects.screening.queue', $project, absolute: false),
                'screening_batches' => route('projects.screening.batches.store', $project, absolute: false),
                'activity' => route('projects.activity.index', $project, absolute: false),
            ],
        ];
    }
}
