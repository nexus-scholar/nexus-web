<?php

namespace App\Actions\Operators;

use App\Actions\Audit\RecordAuditEvent;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class SuspendWorkspace
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    public function handle(Workspace $workspace, User $operator, string $reason, bool $suspended): Workspace
    {
        return DB::transaction(function () use ($operator, $reason, $suspended, $workspace): Workspace {
            $workspace->forceFill([
                'suspended_at' => $suspended ? now() : null,
                'suspended_by' => $suspended ? $operator->id : null,
                'suspended_reason' => $suspended ? $reason : null,
            ])->save();

            $this->audit->handle(
                $suspended ? 'workspace.suspended' : 'workspace.unsuspended',
                $workspace,
                $operator,
                $workspace,
                $reason,
            );

            return $workspace;
        });
    }
}
