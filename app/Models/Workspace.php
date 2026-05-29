<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Enums\WorkspaceMembershipStatus;
use App\Enums\WorkspaceRole;
use App\Enums\WorkspaceType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'owner_user_id',
        'suspended_at',
        'suspended_by',
        'suspended_reason',
    ];

    protected function casts(): array
    {
        return [
            'type' => WorkspaceType::class,
            'suspended_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function suspendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suspended_by');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(WorkspaceMembership::class);
    }

    public function activeMemberships(): HasMany
    {
        return $this->memberships()->where('status', WorkspaceMembershipStatus::Active->value);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_memberships')
            ->withPivot(['role', 'status', 'joined_at', 'removed_at'])
            ->withTimestamps();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(WorkspaceInvitation::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function activeProjects(): HasMany
    {
        return $this->projects()->where('status', '!=', ProjectStatus::Archived->value);
    }

    public function isPersonal(): bool
    {
        return $this->type === WorkspaceType::Personal;
    }

    public function isShared(): bool
    {
        return $this->type === WorkspaceType::Shared;
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    public function activeMembershipFor(User $user): ?WorkspaceMembership
    {
        return $this->memberships()
            ->where('user_id', $user->id)
            ->where('status', WorkspaceMembershipStatus::Active->value)
            ->first();
    }

    public function roleFor(User $user): ?WorkspaceRole
    {
        return $this->activeMembershipFor($user)?->role;
    }
}
