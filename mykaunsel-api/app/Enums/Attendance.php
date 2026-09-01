<?php

namespace App\Enums;

enum Attendance: string
{
    case Attended = 'attended';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';
}
