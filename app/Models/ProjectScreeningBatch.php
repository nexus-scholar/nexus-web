<?php

namespace App\Models;

use App\Enums\ProjectScreeningBatchStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectScreeningBatch extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'project_id',
        'screening_run_id',
        'stage',
        'status',
        'required_reviewer_count',
        'criteria_hash',
        'snapshot_id',
        'source_full_text_batch_id',
        'assignment_policy',
        'counts',
        'created_by',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectScreeningBatchStatus::class,
            'required_reviewer_count' => 'integer',
            'assignment_policy' => 'array',
            'counts' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ProjectScreeningAssignment::class, 'batch_id');
    }

    public function sourceFullTextBatch(): BelongsTo
    {
        return $this->belongsTo(ProjectFullTextBatch::class, 'source_full_text_batch_id');
    }

    public function conflicts(): HasMany
    {
        return $this->hasMany(ProjectScreeningConflict::class, 'batch_id');
    }
}
