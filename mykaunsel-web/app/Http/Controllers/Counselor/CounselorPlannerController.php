<?php

namespace App\Http\Controllers\Counselor;

use App\Enums\SessionMode;
use App\Exceptions\BookingRefused;
use App\Http\Controllers\Controller;
use App\Services\CounselorPlanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The counselor opens and removes their own bookable times. The rules live in CounselorPlanner.
 */
class CounselorPlannerController extends Controller
{
    public function index(Request $request, CounselorPlanner $planner)
    {
        $counselor = $request->user();

        return view('counselors.dashboard.planner', [
            'times' => $planner->upcoming($counselor),
            'contexts' => $planner->contexts($counselor),
            'durations' => CounselorPlanner::durations(),
            'today' => now(config('counselors.directory.slot_timezone'))->format('Y-m-d'),
        ]);
    }

    public function store(Request $request, CounselorPlanner $planner): RedirectResponse
    {
        $input = $request->validate([
            'context' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start' => ['required', 'date_format:H:i'],
            'duration' => ['required', 'integer', Rule::in(CounselorPlanner::durations())],
            'mode' => ['required', Rule::in([SessionMode::Online->value, SessionMode::Physical->value])],
            'location' => ['nullable', 'integer'],
        ]);

        try {
            $planner->open($request->user(), $input);
        } catch (BookingRefused $refused) {
            return back()->withInput()->withErrors(['planner' => $refused->getMessage()]);
        }

        return back()->with('status', __('Time opened.'));
    }

    public function destroy(Request $request, string $publicId, CounselorPlanner $planner): RedirectResponse
    {
        try {
            $planner->remove($request->user(), $publicId);
        } catch (BookingRefused $refused) {
            abort_if($refused->reason === 'planner_not_found', 404);

            return back()->withErrors(['planner' => $refused->getMessage()]);
        }

        return back()->with('status', __('Time removed.'));
    }
}
