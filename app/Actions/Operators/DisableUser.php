<?php

namespace App\Actions\Operators;

use App\Actions\Audit\RecordAuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DisableUser
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    public function handle(User $user, User $operator, string $reason, bool $disabled): User
    {
        return DB::transaction(function () use ($disabled, $operator, $reason, $user): User {
            $user->forceFill([
                'disabled_at' => $disabled ? now() : null,
                'disabled_by' => $disabled ? $operator->id : null,
                'disabled_reason' => $disabled ? $reason : null,
            ])->save();

            $this->audit->handle(
                $disabled ? 'user.disabled' : 'user.enabled',
                $user,
                $operator,
                reason: $reason,
            );

            return $user;
        });
    }
}
