<?php

namespace App\Http\Middleware;

use App\Actions\Workspaces\SetCurrentWorkspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCurrentWorkspace
{
    public function __construct(private readonly SetCurrentWorkspace $setCurrentWorkspace) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $currentWorkspace = $user->currentWorkspace;

        if (
            $currentWorkspace
            && ! $currentWorkspace->isSuspended()
            && $user->belongsToWorkspace($currentWorkspace)
        ) {
            return $next($request);
        }

        $membership = $user->activeWorkspaceMemberships()
            ->whereHas('workspace', fn ($query) => $query->whereNull('suspended_at'))
            ->oldest()
            ->first();

        if (! $membership) {
            abort(403, 'No active workspace is available for this account.');
        }

        $this->setCurrentWorkspace->handle($user, $membership->workspace);

        return $next($request);
    }
}
