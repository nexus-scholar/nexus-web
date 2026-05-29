<?php

namespace App\Actions\Projects;

use App\Models\ProjectProtocol;
use App\Models\ProjectProtocolVersion;
use App\Models\User;

class RecordProjectProtocolVersion
{
    public function handle(ProjectProtocol $protocol, ?User $actor = null, ?string $reason = null): ProjectProtocolVersion
    {
        $protocol->loadMissing('project');

        return ProjectProtocolVersion::create([
            'project_id' => $protocol->project_id,
            'project_protocol_id' => $protocol->id,
            'version' => $protocol->version,
            'status' => $protocol->status,
            'snapshot' => $protocol->snapshot(),
            'reason' => $reason,
            'created_by' => $actor?->id,
        ]);
    }
}
