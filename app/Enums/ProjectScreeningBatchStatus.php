<?php

namespace App\Enums;

enum ProjectScreeningBatchStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Conflicts = 'conflicts';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
