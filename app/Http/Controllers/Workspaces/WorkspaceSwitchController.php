<?php

namespace App\Http\Controllers\Workspaces;

use App\Actions\Workspaces\SetCurrentWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkspaceSwitchController extends Controller
{
    public function __invoke(Request $request, SetCurrentWorkspace $setCurrentWorkspace): RedirectResponse
    {
        $data = $request->validate([
            'workspace_id' => ['required', 'uuid', 'exists:workspaces,id'],
        ]);

        $workspace = Workspace::findOrFail($data['workspace_id']);

        $this->authorize('switch', $workspace);

        $setCurrentWorkspace->handle($request->user(), $workspace);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Workspace switched.')]);

        return back();
    }
}
