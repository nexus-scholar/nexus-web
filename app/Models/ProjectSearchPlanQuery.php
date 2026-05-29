<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectSearchPlanQuery extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'project_search_plan_id',
        'sort_order',
        'query_key',
        'label',
        'query',
        'providers',
        'year_from',
        'year_to',
        'result_limit',
        'include_raw_data',
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
        ];
    }

    public function searchPlan(): BelongsTo
    {
        return $this->belongsTo(ProjectSearchPlan::class, 'project_search_plan_id');
    }

    public function runItems(): HasMany
    {
        return $this->hasMany(ProjectSearchRunItem::class, 'project_search_plan_query_id');
    }
}
