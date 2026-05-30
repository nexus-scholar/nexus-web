<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectCorpusDedupRun extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'project_id',
        'ran_by',
        'status',
        'membership_hash',
        'input_count',
        'representative_count',
        'duplicate_cluster_count',
        'duplicate_member_count',
        'duplicates_removed',
        'duration_ms',
        'policy_stats',
        'metadata',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'input_count' => 'integer',
            'representative_count' => 'integer',
            'duplicate_cluster_count' => 'integer',
            'duplicate_member_count' => 'integer',
            'duplicates_removed' => 'integer',
            'duration_ms' => 'integer',
            'policy_stats' => 'array',
            'metadata' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function runner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ran_by');
    }
}
