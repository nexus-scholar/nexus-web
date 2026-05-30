<?php

namespace App\Models;

use App\Enums\ProjectFullTextItemStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectFullTextItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'id',
        'project_id',
        'batch_id',
        'work_id',
        'screening_decision',
        'status',
        'source_alias',
        'artifact_type',
        'artifact_path',
        'http_status',
        'error_message',
        'metadata',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectFullTextItemStatus::class,
            'metadata' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProjectFullTextBatch::class, 'batch_id');
    }
}
