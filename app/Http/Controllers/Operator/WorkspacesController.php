<?php

namespace App\Http\Controllers\Operator;

use App\Actions\Operators\SuspendWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WorkspacesController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('operator/workspaces', [
            'workspaces' => Workspace::query()
                ->with('owner:id,name,email')
                ->withCount('activeMemberships')
                ->latest()
                ->limit(100)
                ->get()
                ->map(fn (Workspace $workspace) => [
                    'id' => $workspace->id,
                    'name' => $workspace->name,
                    'slug' => $workspace->slug,
                    'type' => $workspace->type->value,
                    'owner' => [
                        'id' => $workspace->owner->id,
                        'name' => $workspace->owner->name,
                        'email' => $workspace->owner->email,
                    ],
                    'active_memberships_count' => $workspace->active_memberships_count,
                    'suspended_at' => $workspace->suspended_at?->toISOString(),
                    'suspended_reason' => $workspace->suspended_reason,
                    'created_at' => $workspace->created_at->toISOString(),
                ]),
        ]);
    }

    public function update(Request $request, Workspace $workspace, SuspendWorkspace $suspendWorkspace): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended'])],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $suspendWorkspace->handle($workspace, $request->user(), $data['reason'], $data['status'] === 'suspended');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Workspace status updated.')]);

        return back();
    }
}
