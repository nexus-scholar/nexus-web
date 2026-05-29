<?php

namespace App\Models;

use App\Enums\SearchRunStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectSearchRunItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'project_search_run_id',
        'project_search_plan_query_id',
        'sort_order',
        'query_key',
        'label',
        'query',
        'providers',
        'year_from',
        'year_to',
        'result_limit',
        'include_raw_data',
        'status',
        'core_search_query_id',
        'total_raw',
        'total_unique',
        'duration_ms',
        'error_message',
        'started_at',
        'completed_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'providers' => 'array',
            'year_from' => 'integer',
            'year_to' => 'integer',
            'result_limit' => 'integer',
            'include_raw_data' => 'boolean',
            'status' => SearchRunStatus::class,
            'total_raw' => 'integer',
            'total_unique' => 'integer',
            'duration_ms' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function searchRun(): BelongsTo
    {
        return $this->belongsTo(ProjectSearchRun::class, 'project_search_run_id');
    }

    public function planQuery(): BelongsTo
    {
        return $this->belongsTo(ProjectSearchPlanQuery::class, 'project_search_plan_query_id');
    }
}
