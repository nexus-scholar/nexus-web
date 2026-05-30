<?php

namespace App\Http\Controllers\Projects;

use App\Actions\Projects\StartProjectFullTextBatch;
use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProjectFullTextBatchController extends Controller
{
    public function store(Request $request, Project $project, StartProjectFullTextBatch $startBatch): RedirectResponse
    {
        $this->authorize('manageFullText', $project);

        $startBatch->handle($project, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Full-text retrieval queued.')]);

        return to_route('projects.full-text.index', $project);
    }
}
