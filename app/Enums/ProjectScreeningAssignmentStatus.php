<?php

namespace App\Enums;

enum ProjectScreeningAssignmentStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Decided = 'decided';
    case Conflict = 'conflict';
    case Resolved = 'resolved';
}
