<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOperator
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isOperator()) {
            abort(403, 'Operator access is required.');
        }

        return $next($request);
    }
}
