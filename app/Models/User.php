<?php

namespace App\Models;

use App\Enums\ProjectMembershipStatus;
use App\Enums\ProjectRole;
use App\Enums\WorkspaceMembershipStatus;
use App\Enums\WorkspaceRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable([
    'name',
    'email',
    'password',
    'current_workspace_id',
    'is_operator',
    'disabled_at',
    'disabled_by',
    'disabled_reason',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    public function currentWorkspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'current_workspace_id');
    }

    public function workspaceMemberships(): HasMany
    {
        return $this->hasMany(WorkspaceMembership::class);
    }

    public function projectMemberships(): HasMany
    {
        return $this->hasMany(ProjectMembership::class);
    }

    public function activeProjectMemberships(): HasMany
    {
        return $this->projectMemberships()
            ->where('status', ProjectMembershipStatus::Active->value);
    }

    public function activeWorkspaceMemberships(): HasMany
    {
        return $this->workspaceMemberships()
            ->where('status', WorkspaceMembershipStatus::Active->value);
    }

    public function oauthIdentities(): HasMany
    {
        return $this->hasMany(OauthIdentity::class);
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(AuditEvent::class, 'actor_user_id');
    }

    public function screeningAssignments(): HasMany
    {
        return $this->hasMany(ProjectScreeningAssignment::class, 'assigned_to');
    }

    public function workspaceRole(Workspace $workspace): ?WorkspaceRole
    {
        return $this->activeWorkspaceMemberships()
            ->where('workspace_id', $workspace->id)
            ->first()
            ?->role;
    }

    public function belongsToWorkspace(Workspace $workspace): bool
    {
        return $this->workspaceRole($workspace) !== null;
    }

    public function projectRole(Project $project): ?ProjectRole
    {
        return $this->activeProjectMemberships()
            ->where('project_id', $project->id)
            ->first()
            ?->role;
    }

    public function belongsToProject(Project $project): bool
    {
        return $this->projectRole($project) !== null;
    }

    public function isWorkspaceOwner(Workspace $workspace): bool
    {
        return $this->workspaceRole($workspace) === WorkspaceRole::Owner;
    }

    public function canManageWorkspaceMembers(Workspace $workspace): bool
    {
        return $this->workspaceRole($workspace)?->canManageMembers() ?? false;
    }

    public function isDisabled(): bool
    {
        return $this->disabled_at !== null;
    }

    public function isOperator(): bool
    {
        return (bool) $this->is_operator;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_operator' => 'boolean',
            'disabled_at' => 'datetime',
        ];
    }
}
