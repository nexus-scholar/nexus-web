<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceSettingsController extends Controller
{
    public function edit(Request $request, Workspace $workspace): Response
    {
        $this->authorize('view', $workspace);

        return Inertia::render('workspaces/settings', [
            'selectedWorkspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'type' => $workspace->type->value,
                'role' => $request->user()->workspaceRole($workspace)?->value,
                'suspended_at' => $workspace->suspended_at?->toISOString(),
                'settings_url' => route('workspaces.settings.edit', $workspace, absolute: false),
                'members_url' => route('workspaces.members.index', $workspace, absolute: false),
            ],
            'can' => [
                'update' => $request->user()->can('update', $workspace),
                'manage_members' => $request->user()->can('manageMembers', $workspace),
            ],
        ]);
    }

    public function update(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('update', $workspace);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
        ]);

        $workspace->update(['name' => $data['name']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Workspace updated.')]);

        return back();
    }
}
