<?php

namespace App\Actions\Projects;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\ProtocolStatus;
use App\Models\Project;
use App\Models\ProjectProtocol;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateProjectProtocol
{
    public function __construct(
        private readonly RecordAuditEvent $audit,
        private readonly RecordProjectProtocolVersion $recordVersion,
    ) {}

    public function handle(Project $project, User $actor, array $data, ?string $reason = null): ProjectProtocol
    {
        $protocol = $project->protocol()->firstOrFail();

        if ($project->isLocked() || $protocol->status === ProtocolStatus::Locked) {
            return $this->amendLockedProtocol($project, $protocol, $actor, $data, $reason);
        }

        return DB::transaction(function () use ($actor, $data, $project, $protocol): ProjectProtocol {
            $project->update([
                'name' => $data['title'],
                'review_type' => $data['review_type'],
            ]);

            $protocol->update([
                ...$this->protocolAttributes($data),
                'status' => ProtocolStatus::Draft,
                'updated_by' => $actor->id,
            ]);

            $this->audit->handle('project.protocol.updated', $protocol, $actor, $project->workspace, project: $project);

            return $protocol->refresh()->load('project');
        });
    }

    private function amendLockedProtocol(
        Project $project,
        ProjectProtocol $protocol,
        User $actor,
        array $data,
        ?string $reason,
    ): ProjectProtocol {
        if (! filled($reason)) {
            throw ValidationException::withMessages([
                'audit_reason' => __('An audit reason is required after the corpus is locked.'),
            ]);
        }

        return DB::transaction(function () use ($actor, $data, $project, $protocol, $reason): ProjectProtocol {
            $project->update([
                'name' => $data['title'],
                'review_type' => $data['review_type'],
            ]);

            $protocol->update([
                ...$this->protocolAttributes($data),
                'status' => ProtocolStatus::Amended,
                'version' => $protocol->version + 1,
                'updated_by' => $actor->id,
            ]);

            $protocol->refresh()->load('project');
            $this->recordVersion->handle($protocol, $actor, $reason);
            $this->audit->handle('project.protocol.amended', $protocol, $actor, $project->workspace, $reason, project: $project);

            return $protocol;
        });
    }

    private function protocolAttributes(array $data): array
    {
        return [
            'title' => $data['title'],
            'research_question' => $data['research_question'] ?? null,
            'background' => $data['background'] ?? null,
            'inclusion_criteria' => $data['inclusion_criteria'] ?? null,
            'exclusion_criteria' => $data['exclusion_criteria'] ?? null,
            'target_providers' => $data['target_providers'] ?? [],
            'date_range_start' => $data['date_range_start'] ?? null,
            'date_range_end' => $data['date_range_end'] ?? null,
            'no_date_limit' => (bool) ($data['no_date_limit'] ?? false),
            'language_policy' => $data['language_policy'] ?? null,
            'min_reviewer_count' => $data['min_reviewer_count'] ?? 2,
            'ai_screening_policy' => $data['ai_screening_policy'] ?? 'human_only',
            'full_text_policy' => $data['full_text_policy'] ?? 'optional',
        ];
    }
}
