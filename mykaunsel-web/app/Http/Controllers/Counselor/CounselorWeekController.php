<?php

namespace App\Http\Controllers\Counselor;

use App\Http\Controllers\Controller;
use App\Services\CounselorWeek;
use Illuminate\Http\Request;

/** The counselor's own calendar for one week (read only). The rules and the data live in CounselorWeek. */
class CounselorWeekController extends Controller
{
    public function index(Request $request, CounselorWeek $weeks)
    {
        // ?week=2026-11-09 (any day of the week wanted). Anything that is not a real day, or is out of reach, shows this week.
        $week = $weeks->forWeek($request->user(), $weeks->mondayOf(is_string($request->query('week')) ? $request->query('week') : null));

        return view('counselors.dashboard.week', ['week' => $week]);
    }
}
