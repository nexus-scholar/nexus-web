<?php

namespace App\Actions\Projects;

use App\Models\Project;
use Nexus\Screening\Domain\ScreeningCriteria;
use Nexus\Screening\Domain\ScreeningStage;

class BuildProjectScreeningCriteria
{
    public function handle(Project $project, ScreeningStage $stage = ScreeningStage::TITLE_ABSTRACT): ScreeningCriteria
    {
        $project->loadMissing('protocol');
        $protocol = $project->protocol;

        return ScreeningCriteria::fromArray([
            'project' => [
                'id' => (string) $project->id,
                'name' => $project->name,
                'review_type' => $project->review_type?->value,
            ],
            'protocol' => [
                'id' => $protocol?->id,
                'version' => $protocol?->version,
                'title' => $protocol?->title,
                'research_question' => $protocol?->research_question,
                'inclusion_criteria' => $protocol?->inclusion_criteria,
                'exclusion_criteria' => $protocol?->exclusion_criteria,
                'language_policy' => $protocol?->language_policy,
                'date_range_start' => $protocol?->date_range_start?->toDateString(),
                'date_range_end' => $protocol?->date_range_end?->toDateString(),
                'no_date_limit' => $protocol?->no_date_limit ?? false,
                'full_text_policy' => $protocol?->full_text_policy,
            ],
            'stage' => $stage->value,
        ]);
    }
}
