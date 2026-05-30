<?php

namespace App\Http\Controllers\Projects;

use App\Actions\Projects\LockProjectCorpus;
use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProjectCorpusLockController extends Controller
{
    public function __invoke(
        Request $request,
        Project $project,
        LockProjectCorpus $lockCorpus,
    ): RedirectResponse {
        $project->load('workspace');

        $this->authorize('lockCorpus', $project);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $lockCorpus->handle($project, $request->user(), $data['reason']);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Corpus locked for screening.'),
        ]);

        return to_route('projects.corpus.index', $project);
    }
}
