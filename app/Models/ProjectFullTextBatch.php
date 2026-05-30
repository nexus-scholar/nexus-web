<?php

namespace App\Models;

use App\Enums\ProjectFullTextBatchStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectFullTextBatch extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'id',
        'project_id',
        'screening_batch_id',
        'snapshot_id',
        'status',
        'candidate_count',
        'success_count',
        'failed_count',
        'skipped_count',
        'manual_needed_count',
        'destination_folder',
        'source_policy',
        'requested_by',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectFullTextBatchStatus::class,
            'candidate_count' => 'integer',
            'success_count' => 'integer',
            'failed_count' => 'integer',
            'skipped_count' => 'integer',
            'manual_needed_count' => 'integer',
            'source_policy' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function screeningBatch(): BelongsTo
    {
        return $this->belongsTo(ProjectScreeningBatch::class, 'screening_batch_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProjectFullTextItem::class, 'batch_id');
    }
}
