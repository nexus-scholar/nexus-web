<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectActivityController extends Controller
{
    public function index(Request $request, Project $project): Response
    {
        $project->load('workspace');

        $this->authorize('viewActivity', $project);

        return Inertia::render('projects/activity', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status->value,
                'urls' => [
                    'overview' => route('projects.show', $project, absolute: false),
                    'protocol' => route('projects.protocol.edit', $project, absolute: false),
                    'search_plan' => route('projects.search-plan.edit', $project, absolute: false),
                    'activity' => route('projects.activity.index', $project, absolute: false),
                ],
            ],
            'events' => $project->auditEvents()
                ->with('actor:id,name,email')
                ->latest('occurred_at')
                ->limit(50)
                ->get()
                ->map(fn ($event): array => [
                    'id' => $event->id,
                    'event_type' => $event->event_type,
                    'reason' => $event->reason,
                    'metadata' => $event->metadata ?? [],
                    'occurred_at' => $event->occurred_at->toISOString(),
                    'actor' => $event->actor ? [
                        'id' => $event->actor->id,
                        'name' => $event->actor->name,
                        'email' => $event->actor->email,
                    ] : null,
                ]),
        ]);
    }
}
