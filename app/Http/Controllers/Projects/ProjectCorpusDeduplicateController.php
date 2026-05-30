<?php

namespace App\Http\Controllers\Projects;

use App\Actions\Projects\RunProjectCorpusDeduplication;
use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProjectCorpusDeduplicateController extends Controller
{
    public function __invoke(
        Request $request,
        Project $project,
        RunProjectCorpusDeduplication $deduplicate,
    ): RedirectResponse {
        $project->load('workspace');

        $this->authorize('deduplicateCorpus', $project);

        $run = $deduplicate->handle($project, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Deduplication completed. :count duplicate clusters found.', [
                'count' => $run->duplicate_cluster_count,
            ]),
        ]);

        return to_route('projects.deduplication.index', $project);
    }
}
