<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Queries\Projects\ProjectFullTextScreeningReadModel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectFullTextScreeningController extends Controller
{
    public function index(
        Request $request,
        Project $project,
        ProjectFullTextScreeningReadModel $screening,
    ): Response {
        $project->load('workspace');

        $this->authorize('viewFullTextScreening', $project);

        return Inertia::render('projects/full-text-screening', [
            'project' => $this->projectPayload($project),
            'screening' => $screening->forProject(
                $project,
                $request->user(),
                $request->query('conflict') ? (string) $request->query('conflict') : null,
            ),
            'can' => [
                'view_full_text_screening' => $request->user()->can('viewFullTextScreening', $project),
                'manage_full_text_screening' => $request->user()->can('manageFullTextScreening', $project),
                'screen_assigned_full_text' => $request->user()->can('screenAssignedFullText', $project),
                'resolve_full_text_screening_conflict' => $request->user()->can('resolveFullTextScreeningConflict', $project),
                'view_full_text' => $request->user()->can('viewFullText', $project),
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
                'screening' => route('projects.screening.index', $project, absolute: false),
                'screening_queue' => route('projects.screening.queue', $project, absolute: false),
                'full_text' => route('projects.full-text.index', $project, absolute: false),
                'full_text_screening' => route('projects.full-text-screening.index', $project, absolute: false),
                'full_text_screening_queue' => route('projects.full-text-screening.queue', $project, absolute: false),
                'full_text_screening_batches' => route('projects.full-text-screening.batches.store', $project, absolute: false),
                'activity' => route('projects.activity.index', $project, absolute: false),
            ],
        ];
    }
}
