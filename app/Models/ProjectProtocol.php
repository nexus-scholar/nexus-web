<?php

namespace App\Models;

use App\Enums\ProtocolStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectProtocol extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'project_id',
        'status',
        'version',
        'title',
        'research_question',
        'background',
        'inclusion_criteria',
        'exclusion_criteria',
        'target_providers',
        'date_range_start',
        'date_range_end',
        'no_date_limit',
        'language_policy',
        'min_reviewer_count',
        'ai_screening_policy',
        'full_text_policy',
        'created_by',
        'updated_by',
        'completed_at',
        'locked_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProtocolStatus::class,
            'version' => 'integer',
            'target_providers' => 'array',
            'date_range_start' => 'date',
            'date_range_end' => 'date',
            'no_date_limit' => 'boolean',
            'min_reviewer_count' => 'integer',
            'completed_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ProjectProtocolVersion::class);
    }

    public function readinessMissingFields(): array
    {
        $project = $this->project;

        $checks = [
            'title' => filled($this->title),
            'review_type' => filled($project?->review_type?->value),
            'research_question' => filled($this->research_question),
            'background' => filled($this->background),
            'inclusion_criteria' => filled($this->inclusion_criteria),
            'exclusion_criteria' => filled($this->exclusion_criteria),
            'target_providers' => count($this->target_providers ?? []) > 0,
            'date_range' => $this->no_date_limit || ($this->date_range_start && $this->date_range_end),
            'language_policy' => filled($this->language_policy),
            'min_reviewer_count' => $this->min_reviewer_count >= 1,
            'ai_screening_policy' => filled($this->ai_screening_policy),
            'full_text_policy' => filled($this->full_text_policy),
        ];

        return collect($checks)
            ->reject(fn (bool $complete): bool => $complete)
            ->keys()
            ->all();
    }

    public function isReadyForSearch(): bool
    {
        return $this->readinessMissingFields() === [];
    }

    public function snapshot(): array
    {
        return [
            'project' => [
                'id' => $this->project_id,
                'name' => $this->project?->name,
                'review_type' => $this->project?->review_type?->value,
                'status' => $this->project?->status?->value,
            ],
            'protocol' => [
                'id' => $this->id,
                'status' => $this->status->value,
                'version' => $this->version,
                'title' => $this->title,
                'research_question' => $this->research_question,
                'background' => $this->background,
                'inclusion_criteria' => $this->inclusion_criteria,
                'exclusion_criteria' => $this->exclusion_criteria,
                'target_providers' => $this->target_providers ?? [],
                'date_range_start' => $this->date_range_start?->toDateString(),
                'date_range_end' => $this->date_range_end?->toDateString(),
                'no_date_limit' => $this->no_date_limit,
                'language_policy' => $this->language_policy,
                'min_reviewer_count' => $this->min_reviewer_count,
                'ai_screening_policy' => $this->ai_screening_policy,
                'full_text_policy' => $this->full_text_policy,
            ],
        ];
    }
}
