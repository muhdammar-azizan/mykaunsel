<?php

namespace App\Enums;

enum BookingMode: string
{
    case SlotBased = 'slot_based';
    case RequestBased = 'request_based';
}
