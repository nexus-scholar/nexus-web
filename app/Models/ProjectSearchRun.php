<?php

namespace App\Models;

use App\Enums\SearchRunStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectSearchRun extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'project_id',
        'project_search_plan_id',
        'status',
        'plan_version',
        'query_count',
        'failure_count',
        'total_raw',
        'total_unique',
        'error_message',
        'metadata',
        'requested_by',
        'started_at',
        'completed_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SearchRunStatus::class,
            'plan_version' => 'integer',
            'query_count' => 'integer',
            'failure_count' => 'integer',
            'total_raw' => 'integer',
            'total_unique' => 'integer',
            'metadata' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function searchPlan(): BelongsTo
    {
        return $this->belongsTo(ProjectSearchPlan::class, 'project_search_plan_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProjectSearchRunItem::class)->orderBy('sort_order');
    }
}
