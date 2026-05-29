<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function view(User $user, Project $project): bool
    {
        return $user->belongsToProject($project)
            || $this->administersProjectWorkspace($user, $project);
    }

    public function updateProtocol(User $user, Project $project): bool
    {
        if ($project->workspace?->isSuspended()) {
            return false;
        }

        return $user->projectRole($project) === ProjectRole::Owner
            || $this->administersProjectWorkspace($user, $project);
    }

    public function completeProtocol(User $user, Project $project): bool
    {
        return $this->updateProtocol($user, $project);
    }

    public function viewSearchPlan(User $user, Project $project): bool
    {
        return $this->view($user, $project);
    }

    public function updateSearchPlan(User $user, Project $project): bool
    {
        if ($project->isLocked() || $project->workspace?->isSuspended()) {
            return false;
        }

        return $user->projectRole($project) === ProjectRole::Owner
            || $this->administersProjectWorkspace($user, $project);
    }

    public function runSearch(User $user, Project $project): bool
    {
        return $this->updateSearchPlan($user, $project);
    }

    public function viewActivity(User $user, Project $project): bool
    {
        return $this->view($user, $project);
    }

    private function administersProjectWorkspace(User $user, Project $project): bool
    {
        $workspace = $project->workspace;

        if (! $workspace || $workspace->isSuspended()) {
            return false;
        }

        return in_array($user->workspaceRole($workspace), [WorkspaceRole::Owner, WorkspaceRole::Admin], true);
    }
}
