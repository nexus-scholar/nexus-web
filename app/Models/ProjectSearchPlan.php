<?php

namespace App\Models;

use App\Enums\SearchPlanStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectSearchPlan extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'project_id',
        'status',
        'version',
        'default_providers',
        'default_year_from',
        'default_year_to',
        'default_result_limit',
        'include_raw_data',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => SearchPlanStatus::class,
            'version' => 'integer',
            'default_providers' => 'array',
            'default_year_from' => 'integer',
            'default_year_to' => 'integer',
            'default_result_limit' => 'integer',
            'include_raw_data' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function queries(): HasMany
    {
        return $this->hasMany(ProjectSearchPlanQuery::class)->orderBy('sort_order');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ProjectSearchRun::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
