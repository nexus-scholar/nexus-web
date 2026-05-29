<?php

use App\Http\Controllers\Operator\UsersController as OperatorUsersController;
use App\Http\Controllers\Operator\WorkspacesController as OperatorWorkspacesController;
use App\Http\Controllers\Workspaces\WorkspaceController;
use App\Http\Controllers\Workspaces\WorkspaceInvitationController;
use App\Http\Controllers\Workspaces\WorkspaceMembersController;
use App\Http\Controllers\Workspaces\WorkspaceSettingsController;
use App\Http\Controllers\Workspaces\WorkspaceSwitchController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified', 'not_disabled', 'workspace.ready'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::post('workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::post('workspaces/switch', WorkspaceSwitchController::class)->name('workspaces.switch');
    Route::get('workspaces/{workspace}/settings', [WorkspaceSettingsController::class, 'edit'])->name('workspaces.settings.edit');
    Route::patch('workspaces/{workspace}/settings', [WorkspaceSettingsController::class, 'update'])->name('workspaces.settings.update');
    Route::get('workspaces/{workspace}/members', [WorkspaceMembersController::class, 'index'])->name('workspaces.members.index');
    Route::post('workspaces/{workspace}/invitations', [WorkspaceInvitationController::class, 'store'])->name('workspaces.invitations.store');
    Route::patch('workspaces/{workspace}/members/{user}/role', [WorkspaceMembersController::class, 'update'])->name('workspaces.members.update');
    Route::delete('workspaces/{workspace}/members/{user}', [WorkspaceMembersController::class, 'destroy'])->name('workspaces.members.destroy');
});

Route::middleware(['auth', 'verified', 'not_disabled'])->group(function () {
    Route::post('workspaces/invitations/{invitation}/accept', [WorkspaceInvitationController::class, 'accept'])->name('workspaces.invitations.accept');
});

Route::middleware(['auth', 'verified', 'not_disabled', 'operator'])
    ->prefix('operator')
    ->name('operator.')
    ->group(function () {
        Route::get('users', [OperatorUsersController::class, 'index'])->name('users.index');
        Route::patch('users/{user}/status', [OperatorUsersController::class, 'update'])->name('users.update');
        Route::get('workspaces', [OperatorWorkspacesController::class, 'index'])->name('workspaces.index');
        Route::patch('workspaces/{workspace}/status', [OperatorWorkspacesController::class, 'update'])->name('workspaces.update');
    });

require __DIR__.'/settings.php';
