<?php

namespace App\Models;

use App\Enums\ProjectScreeningAssignmentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectScreeningAssignment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'project_id',
        'batch_id',
        'work_id',
        'assigned_to',
        'assigned_by',
        'stage',
        'status',
        'screening_decision_id',
        'sort_order',
        'assigned_at',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectScreeningAssignmentStatus::class,
            'sort_order' => 'integer',
            'assigned_at' => 'datetime',
            'decided_at' => 'datetime',
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

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
