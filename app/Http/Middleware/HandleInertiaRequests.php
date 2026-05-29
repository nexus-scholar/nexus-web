<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'workspace' => fn () => $this->workspacePayload($request),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array{current: array<string, mixed>|null, memberships: array<int, array<string, mixed>>}
     */
    private function workspacePayload(Request $request): array
    {
        $user = $request->user();

        if (! $user) {
            return ['current' => null, 'memberships' => []];
        }

        $current = $user->currentWorkspace;

        return [
            'current' => $current instanceof Workspace ? $this->workspaceSummary($current, $user) : null,
            'memberships' => $user->activeWorkspaceMemberships()
                ->with('workspace')
                ->whereHas('workspace', fn ($query) => $query->whereNull('suspended_at'))
                ->oldest()
                ->get()
                ->map(fn ($membership) => [
                    'id' => $membership->id,
                    'role' => $membership->role->value,
                    'role_label' => $membership->role->label(),
                    'workspace' => $this->workspaceSummary($membership->workspace, $user),
                ])
                ->values()
                ->all(),
        ];
    }

    private function workspaceSummary(Workspace $workspace, User $user): array
    {
        return [
            'id' => $workspace->id,
            'name' => $workspace->name,
            'slug' => $workspace->slug,
            'type' => $workspace->type->value,
            'role' => $user->workspaceRole($workspace)?->value,
            'suspended_at' => $workspace->suspended_at?->toISOString(),
            'settings_url' => route('workspaces.settings.edit', $workspace, absolute: false),
            'members_url' => route('workspaces.members.index', $workspace, absolute: false),
        ];
    }
}
