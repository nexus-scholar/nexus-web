<?php

namespace App\Enums;

enum ProjectFullTextItemStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Success = 'success';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case ManualNeeded = 'manual_needed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Running => 'Running',
            self::Success => 'Success',
            self::Failed => 'Failed',
            self::Skipped => 'Skipped',
            self::ManualNeeded => 'Manual needed',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Success, self::Failed, self::Skipped, self::ManualNeeded], true);
    }
}
