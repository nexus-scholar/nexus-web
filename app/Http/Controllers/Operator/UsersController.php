<?php

namespace App\Http\Controllers\Operator;

use App\Actions\Operators\DisableUser;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UsersController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('operator/users', [
            'users' => User::query()
                ->latest()
                ->limit(100)
                ->get(['id', 'name', 'email', 'email_verified_at', 'is_operator', 'disabled_at', 'disabled_reason', 'created_at'])
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at?->toISOString(),
                    'is_operator' => $user->is_operator,
                    'disabled_at' => $user->disabled_at?->toISOString(),
                    'disabled_reason' => $user->disabled_reason,
                    'created_at' => $user->created_at->toISOString(),
                ]),
        ]);
    }

    public function update(Request $request, User $user, DisableUser $disableUser): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'disabled'])],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        abort_if($request->user()->is($user) && $data['status'] === 'disabled', 422, 'Operators cannot disable their own account.');

        $disableUser->handle($user, $request->user(), $data['reason'], $data['status'] === 'disabled');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User status updated.')]);

        return back();
    }
}
