<?php

namespace App\Actions\Audit;

use App\Models\AuditEvent;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;

class RecordAuditEvent
{
    public function handle(
        string $eventType,
        Model|string $target,
        ?User $actor = null,
        ?Workspace $workspace = null,
        ?string $reason = null,
        array $metadata = [],
        ?Project $project = null,
    ): AuditEvent {
        return AuditEvent::create([
            'workspace_id' => $workspace?->id,
            'project_id' => $project?->id ?? ($target instanceof Project ? $target->id : null),
            'actor_user_id' => $actor?->id,
            'event_type' => $eventType,
            'target_type' => $target instanceof Model ? $target::class : 'system',
            'target_id' => $target instanceof Model ? (string) $target->getKey() : $target,
            'reason' => $reason,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }
}
