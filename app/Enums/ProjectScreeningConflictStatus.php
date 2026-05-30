<?php

namespace App\Enums;

enum ProjectScreeningConflictStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
}
