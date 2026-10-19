<?php

namespace App\Enums;

enum CalendarEntryType: string
{
    case AvailableSlot = 'available_slot';
    case PersonalBlock = 'personal_block';
}
