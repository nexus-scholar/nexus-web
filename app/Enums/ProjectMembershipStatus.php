<?php

namespace App\Enums;

enum ProjectMembershipStatus: string
{
    case Active = 'active';
    case Removed = 'removed';
}
