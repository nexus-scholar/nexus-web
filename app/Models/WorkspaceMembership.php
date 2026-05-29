<?php

namespace App\Models;

use App\Enums\WorkspaceMembershipStatus;
use App\Enums\WorkspaceRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceMembership extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'user_id',
        'role',
        'status',
        'joined_at',
        'removed_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => WorkspaceRole::class,
            'status' => WorkspaceMembershipStatus::class,
            'joined_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === WorkspaceMembershipStatus::Active;
    }

    public function isOwner(): bool
    {
        return $this->role === WorkspaceRole::Owner;
    }

    public function isAdmin(): bool
    {
        return $this->role === WorkspaceRole::Admin;
    }
}
