<?php

namespace App\Http\Controllers\Counselor;

use App\Http\Controllers\Controller;
use App\Services\CounselorScheduleEvents;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * The data behind the Schedule calendar (what the calendar asks for as the counselor moves between weeks). Only the signed-in counselor's own
 * entries, for at most CounselorScheduleEvents::MAX_DAYS days at a time, never cached. The rules and the shape of an event are in
 * CounselorScheduleEvents.
 */
class CounselorScheduleController extends Controller
{
    public function events(Request $request, CounselorScheduleEvents $schedule): JsonResponse
    {
        $input = $request->validate(['start' => ['required', 'string', 'max:40'], 'end' => ['required', 'string', 'max:40']]);

        try {
            $from = CarbonImmutable::parse($input['start']);
            $to = CarbonImmutable::parse($input['end']);
        } catch (Throwable) {
            abort(422, 'Not a date.');
        }

        abort_if($to->lte($from) || $from->diffInDays($to) > CounselorScheduleEvents::MAX_DAYS, 422, 'Ask for a shorter stretch of days.');

        return response()->json($schedule->between($request->user(), $from, $to))->header('Cache-Control', 'private, no-store');
    }

    /** The old Calendar address: the calendar now lives on the Schedule page. `?week=` (any day of the wanted week) becomes `?date=`. */
    public function week(Request $request): RedirectResponse
    {
        $week = $request->query('week');

        return redirect()->route('counselor.planner', is_string($week) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $week) === 1 ? ['date' => $week] : []);
    }
}
