<?php

namespace App\Actions\Projects;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\ProjectStatus;
use App\Enums\ProtocolStatus;
use App\Models\Project;
use App\Models\ProjectProtocol;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteProjectProtocol
{
    public function __construct(
        private readonly RecordAuditEvent $audit,
        private readonly EnsureProjectSearchPlan $ensureSearchPlan,
        private readonly RecordProjectProtocolVersion $recordVersion,
    ) {}

    public function handle(Project $project, User $actor): ProjectProtocol
    {
        $protocol = $project->protocol()->firstOrFail()->load('project');
        $missing = $protocol->readinessMissingFields();

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'protocol' => __('Complete required protocol fields before marking the protocol complete.'),
                'missing_fields' => implode(', ', $missing),
            ]);
        }

        return DB::transaction(function () use ($actor, $project, $protocol): ProjectProtocol {
            $project->update(['status' => ProjectStatus::ReadyForSearch]);

            $protocol->update([
                'status' => ProtocolStatus::Complete,
                'version' => $protocol->version + 1,
                'completed_at' => now(),
                'updated_by' => $actor->id,
            ]);

            $protocol->refresh()->load('project');
            $this->recordVersion->handle($protocol, $actor, 'Protocol marked complete.');
            $this->ensureSearchPlan->handle($project->refresh()->load('protocol'), $actor);
            $this->audit->handle('project.protocol.completed', $protocol, $actor, $project->workspace, project: $project);

            return $protocol;
        });
    }
}
