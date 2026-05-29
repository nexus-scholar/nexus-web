<?php

namespace App\Http\Controllers\Workspaces;

use App\Actions\Workspaces\CreateSharedWorkspace;
use App\Actions\Workspaces\SetCurrentWorkspace;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkspaceController extends Controller
{
    public function store(
        Request $request,
        CreateSharedWorkspace $createSharedWorkspace,
        SetCurrentWorkspace $setCurrentWorkspace,
    ): RedirectResponse {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
        ]);

        $workspace = $createSharedWorkspace->handle($request->user(), $data['name']);
        $setCurrentWorkspace->handle($request->user(), $workspace);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Workspace created.')]);

        return to_route('dashboard');
    }
}
