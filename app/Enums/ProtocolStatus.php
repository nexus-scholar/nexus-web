<?php

namespace App\Enums;

enum ProtocolStatus: string
{
    case Draft = 'draft';
    case Complete = 'complete';
    case Locked = 'locked';
    case Amended = 'amended';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Complete => 'Complete',
            self::Locked => 'Locked',
            self::Amended => 'Amended',
        };
    }
}
