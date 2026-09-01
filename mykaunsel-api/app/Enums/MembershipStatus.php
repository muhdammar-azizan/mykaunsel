<?php

namespace App\Enums;

enum MembershipStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Alumni = 'alumni';
    case NoticePeriod = 'notice_period';
    case Offboarded = 'offboarded';
}
