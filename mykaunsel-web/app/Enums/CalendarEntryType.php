<?php

namespace App\Enums;

enum CalendarEntryType: string
{
    case AvailableSlot = 'available_slot';
    case PersonalBlock = 'personal_block';

    /** The time of a Mode B session the counselor approved. Never offered to anyone (it is not a slot); busy while its booking is. */
    case RequestedSession = 'requested_session';
}
