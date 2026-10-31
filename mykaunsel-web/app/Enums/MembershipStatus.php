<?php

namespace App\Enums;

enum MembershipStatus: string
{
    case Active = 'active';

    /** Joined with a reference code and waiting for the organization's HR. Gives no access at all until approved. */
    case PendingApproval = 'pending_approval';

    case Suspended = 'suspended';
    case Alumni = 'alumni';
    case NoticePeriod = 'notice_period';
    case Offboarded = 'offboarded';
}
