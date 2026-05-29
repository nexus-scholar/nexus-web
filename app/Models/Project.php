<?php

namespace App\Models;

use App\Enums\ProjectMembershipStatus;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\ReviewType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Project extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'workspace_id',
        'owner_user_id',
        'name',
        'slug',
        'description',
        'review_type',
        'status',
        'metadata',
        'locked_at',
        'locked_by',
        'lock_reason',
        'unlocked_at',
        'unlocked_by',
        'unlock_reason',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'review_type' => ReviewType::class,
            'status' => ProjectStatus::class,
            'locked_at' => 'datetime',
            'unlocked_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ProjectMembership::class);
    }

    public function activeMemberships(): HasMany
    {
        return $this->memberships()->where('status', ProjectMembershipStatus::Active->value);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_memberships')
            ->withPivot(['role', 'status', 'joined_at', 'removed_at'])
            ->withTimestamps();
    }

    public function protocol(): HasOne
    {
        return $this->hasOne(ProjectProtocol::class);
    }

    public function protocolVersions(): HasMany
    {
        return $this->hasMany(ProjectProtocolVersion::class);
    }

    public function searchPlan(): HasOne
    {
        return $this->hasOne(ProjectSearchPlan::class);
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(AuditEvent::class);
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null || in_array($this->status, [
            ProjectStatus::Locked,
            ProjectStatus::LockedCorpus,
        ], true);
    }

    public function activeMembershipFor(User $user): ?ProjectMembership
    {
        return $this->memberships()
            ->where('user_id', $user->id)
            ->where('status', ProjectMembershipStatus::Active->value)
            ->first();
    }

    public function roleFor(User $user): ?ProjectRole
    {
        return $this->activeMembershipFor($user)?->role;
    }
}
