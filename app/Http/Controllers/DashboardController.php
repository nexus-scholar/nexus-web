<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $workspace = $request->user()->currentWorkspace;

        return Inertia::render('dashboard', [
            'projects' => $workspace
                ? $workspace->projects()
                    ->with(['protocol', 'activeMemberships'])
                    ->where('status', '!=', ProjectStatus::Archived->value)
                    ->latest()
                    ->get()
                    ->filter(fn (Project $project): bool => $request->user()->can('view', $project))
                    ->values()
                    ->map(fn (Project $project): array => [
                        'id' => $project->id,
                        'name' => $project->name,
                        'slug' => $project->slug,
                        'review_type' => $project->review_type?->value,
                        'review_type_label' => $project->review_type?->label(),
                        'status' => $project->status->value,
                        'status_label' => $project->status->label(),
                        'protocol_status' => $project->protocol?->status->value,
                        'protocol_status_label' => $project->protocol?->status->label(),
                        'members_count' => $project->activeMemberships->count(),
                        'overview_url' => route('projects.show', $project, absolute: false),
                        'protocol_url' => route('projects.protocol.edit', $project, absolute: false),
                    ])
                    ->all()
                : [],
            'can' => [
                'create_project' => $workspace ? $request->user()->can('createProject', $workspace) : false,
            ],
        ]);
    }
}
