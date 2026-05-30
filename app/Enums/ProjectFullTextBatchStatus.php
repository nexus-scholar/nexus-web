<?php

namespace App\Enums;

enum ProjectFullTextBatchStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case CompletedWithFailures = 'completed_with_failures';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Running => 'Running',
            self::Completed => 'Completed',
            self::CompletedWithFailures => 'Completed with failures',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Queued, self::Running], true);
    }
}
