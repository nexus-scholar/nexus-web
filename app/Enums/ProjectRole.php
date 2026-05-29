<?php

namespace App\Enums;

enum ProjectRole: string
{
    case Owner = 'owner';
    case Reviewer = 'reviewer';
    case Adjudicator = 'adjudicator';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Reviewer => 'Reviewer',
            self::Adjudicator => 'Adjudicator',
            self::Viewer => 'Viewer',
        };
    }

    public function canEditProtocol(): bool
    {
        return $this === self::Owner;
    }
}
