<?php

namespace App\Models;

use App\Enums\ProjectScreeningConflictStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectScreeningConflict extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'project_id',
        'batch_id',
        'work_id',
        'stage',
        'status',
        'decision_ids',
        'resolved_decision_id',
        'resolved_by',
        'resolution_reason',
        'opened_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectScreeningConflictStatus::class,
            'decision_ids' => 'array',
            'opened_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProjectScreeningBatch::class, 'batch_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
