<?php

namespace App\Enums;

enum WorkspaceMembershipStatus: string
{
    case Active = 'active';
    case Removed = 'removed';
}
