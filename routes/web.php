<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Operator\UsersController as OperatorUsersController;
use App\Http\Controllers\Operator\WorkspacesController as OperatorWorkspacesController;
use App\Http\Controllers\Projects\ProjectActivityController;
use App\Http\Controllers\Projects\ProjectController;
use App\Http\Controllers\Projects\ProjectCorpusController;
use App\Http\Controllers\Projects\ProjectCorpusDeduplicateController;
use App\Http\Controllers\Projects\ProjectCorpusDeduplicationController;
use App\Http\Controllers\Projects\ProjectCorpusLockController;
use App\Http\Controllers\Projects\ProjectFullTextArtifactController;
use App\Http\Controllers\Projects\ProjectFullTextBatchController;
use App\Http\Controllers\Projects\ProjectFullTextController;
use App\Http\Controllers\Projects\ProjectFullTextScreeningAssignmentDecisionController;
use App\Http\Controllers\Projects\ProjectFullTextScreeningBatchController;
use App\Http\Controllers\Projects\ProjectFullTextScreeningConflictResolutionController;
use App\Http\Controllers\Projects\ProjectFullTextScreeningController;
use App\Http\Controllers\Projects\ProjectFullTextScreeningQueueController;
use App\Http\Controllers\Projects\ProjectProtocolController;
use App\Http\Controllers\Projects\ProjectScreeningAssignmentDecisionController;
use App\Http\Controllers\Projects\ProjectScreeningBatchController;
use App\Http\Controllers\Projects\ProjectScreeningConflictResolutionController;
use App\Http\Controllers\Projects\ProjectScreeningController;
use App\Http\Controllers\Projects\ProjectScreeningQueueController;
use App\Http\Controllers\Projects\ProjectSearchPlanController;
use App\Http\Controllers\Projects\ProjectSearchRunController;
use App\Http\Controllers\Workspaces\WorkspaceController;
use App\Http\Controllers\Workspaces\WorkspaceInvitationController;
use App\Http\Controllers\Workspaces\WorkspaceMembersController;
use App\Http\Controllers\Workspaces\WorkspaceSettingsController;
use App\Http\Controllers\Workspaces\WorkspaceSwitchController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified', 'not_disabled', 'workspace.ready'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::post('workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::post('workspaces/switch', WorkspaceSwitchController::class)->name('workspaces.switch');
    Route::get('workspaces/{workspace}/settings', [WorkspaceSettingsController::class, 'edit'])->name('workspaces.settings.edit');
    Route::patch('workspaces/{workspace}/settings', [WorkspaceSettingsController::class, 'update'])->name('workspaces.settings.update');
    Route::get('workspaces/{workspace}/members', [WorkspaceMembersController::class, 'index'])->name('workspaces.members.index');
    Route::post('workspaces/{workspace}/invitations', [WorkspaceInvitationController::class, 'store'])->name('workspaces.invitations.store');
    Route::patch('workspaces/{workspace}/members/{user}/role', [WorkspaceMembersController::class, 'update'])->name('workspaces.members.update');
    Route::delete('workspaces/{workspace}/members/{user}', [WorkspaceMembersController::class, 'destroy'])->name('workspaces.members.destroy');

    Route::get('projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::get('projects/{project}/protocol', [ProjectProtocolController::class, 'edit'])->name('projects.protocol.edit');
    Route::patch('projects/{project}/protocol', [ProjectProtocolController::class, 'update'])->name('projects.protocol.update');
    Route::get('projects/{project}/search-plan', [ProjectSearchPlanController::class, 'edit'])->name('projects.search-plan.edit');
    Route::patch('projects/{project}/search-plan', [ProjectSearchPlanController::class, 'update'])->name('projects.search-plan.update');
    Route::post('projects/{project}/search-runs', [ProjectSearchRunController::class, 'store'])->name('projects.search-runs.store');
    Route::get('projects/{project}/search-runs/{searchRun}', [ProjectSearchRunController::class, 'show'])->name('projects.search-runs.show');
    Route::get('projects/{project}/corpus', [ProjectCorpusController::class, 'index'])->name('projects.corpus.index');
    Route::get('projects/{project}/deduplication', [ProjectCorpusDeduplicationController::class, 'index'])->name('projects.deduplication.index');
    Route::post('projects/{project}/corpus/deduplicate', ProjectCorpusDeduplicateController::class)->name('projects.corpus.deduplicate');
    Route::post('projects/{project}/corpus/lock', ProjectCorpusLockController::class)->name('projects.corpus.lock');
    Route::get('projects/{project}/screening', [ProjectScreeningController::class, 'index'])->name('projects.screening.index');
    Route::post('projects/{project}/screening/batches', [ProjectScreeningBatchController::class, 'store'])->name('projects.screening.batches.store');
    Route::get('projects/{project}/screening/queue', [ProjectScreeningQueueController::class, 'index'])->name('projects.screening.queue');
    Route::post('projects/{project}/screening/assignments/{assignment}/decision', [ProjectScreeningAssignmentDecisionController::class, 'store'])->name('projects.screening.assignments.decision');
    Route::get('projects/{project}/screening/conflicts', [ProjectScreeningController::class, 'index'])->name('projects.screening.conflicts.index');
    Route::post('projects/{project}/screening/conflicts/{conflict}/resolve', [ProjectScreeningConflictResolutionController::class, 'store'])->name('projects.screening.conflicts.resolve');
    Route::get('projects/{project}/full-text', [ProjectFullTextController::class, 'index'])->name('projects.full-text.index');
    Route::post('projects/{project}/full-text/batches', [ProjectFullTextBatchController::class, 'store'])->name('projects.full-text.batches.store');
    Route::get('projects/{project}/full-text/artifacts/{item}', [ProjectFullTextArtifactController::class, 'show'])->name('projects.full-text.artifacts.show');
    Route::get('projects/{project}/full-text-screening', [ProjectFullTextScreeningController::class, 'index'])->name('projects.full-text-screening.index');
    Route::post('projects/{project}/full-text-screening/batches', [ProjectFullTextScreeningBatchController::class, 'store'])->name('projects.full-text-screening.batches.store');
    Route::get('projects/{project}/full-text-screening/queue', [ProjectFullTextScreeningQueueController::class, 'index'])->name('projects.full-text-screening.queue');
    Route::post('projects/{project}/full-text-screening/assignments/{assignment}/decision', [ProjectFullTextScreeningAssignmentDecisionController::class, 'store'])->name('projects.full-text-screening.assignments.decision');
    Route::get('projects/{project}/full-text-screening/conflicts', [ProjectFullTextScreeningController::class, 'index'])->name('projects.full-text-screening.conflicts.index');
    Route::post('projects/{project}/full-text-screening/conflicts/{conflict}/resolve', [ProjectFullTextScreeningConflictResolutionController::class, 'store'])->name('projects.full-text-screening.conflicts.resolve');
    Route::get('projects/{project}/activity', [ProjectActivityController::class, 'index'])->name('projects.activity.index');
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
