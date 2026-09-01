<?php

namespace App\Enums;

enum FollowUpStatus: string
{
    case None = 'none';
    case FollowUpNeeded = 'follow_up_needed';
    case Referred = 'referred';
}
