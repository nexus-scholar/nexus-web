<?php

namespace App\Models;

use App\Enums\ProjectMembershipStatus;
use App\Enums\ProjectRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMembership extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'user_id',
        'role',
        'status',
        'joined_at',
        'removed_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => ProjectRole::class,
            'status' => ProjectMembershipStatus::class,
            'joined_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === ProjectMembershipStatus::Active;
    }

    public function canEditProtocol(): bool
    {
        return $this->isActive() && $this->role->canEditProtocol();
    }
}
