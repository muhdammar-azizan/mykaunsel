<?php

namespace App\Http\Controllers\Counselor;

use App\Enums\SessionMode;
use App\Exceptions\BookingRefused;
use App\Http\Controllers\Controller;
use App\Services\CounselorBlocks;
use App\Services\CounselorPlanner;
use App\Services\CounselorWeeklyHours;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The counselor opens and removes their own bookable times. The rules live in CounselorPlanner.
 */
class CounselorPlannerController extends Controller
{
    public function index(Request $request, CounselorPlanner $planner, CounselorWeeklyHours $hours, CounselorBlocks $blocks)
    {
        $counselor = $request->user();

        return view('counselors.dashboard.planner', [
            'times' => $planner->upcoming($counselor),
            'blocks' => $blocks->upcoming($counselor),
            'lines' => $hours->lines($counselor),
            'maxWeeks' => CounselorWeeklyHours::MAX_WEEKS,
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

    public function storeHours(Request $request, CounselorWeeklyHours $hours): RedirectResponse
    {
        $input = $request->validate([
            'context' => ['required', 'integer'],
            'weekday' => ['required', 'integer', 'between:1,7'],
            'from' => ['required', 'date_format:H:i'],
            'to' => ['required', 'date_format:H:i'],
            'duration' => ['required', 'integer', Rule::in(CounselorPlanner::durations())],
            'mode' => ['required', Rule::in([SessionMode::Online->value, SessionMode::Physical->value])],
            'location' => ['nullable', 'integer'],
        ]);

        try {
            $hours->add($request->user(), $input);
        } catch (BookingRefused $refused) {
            return back()->withInput()->withErrors(['planner' => $refused->getMessage()]);
        }

        return back()->with('status', __('Weekly hours saved. Use "Open the next weeks" to make the times bookable.'));
    }

    public function storeBlock(Request $request, CounselorBlocks $blocks): RedirectResponse
    {
        $input = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'all_day' => ['nullable', 'boolean'],
            'start' => ['nullable', 'required_without:all_day', 'date_format:H:i'],
            'end' => ['nullable', 'required_without:all_day', 'date_format:H:i'],
            'title' => ['nullable', 'string', 'max:100'],
        ]);
        $input['all_day'] = $request->boolean('all_day');
        $input['to'] = ($input['to'] ?? null) ?: $input['from'];

        try {
            $days = $blocks->add($request->user(), $input);
        } catch (BookingRefused $refused) {
            return back()->withInput()->withErrors(['block' => $refused->getMessage()]);
        }

        return back()->with('status', trans_choice(':count day blocked. Times open for booking inside it can not be booked until you remove the block.|:count days blocked. Times open for booking inside them can not be booked until you remove the block.', $days, ['count' => $days]));
    }

    public function destroyBlock(Request $request, string $publicId, CounselorBlocks $blocks): RedirectResponse
    {
        try {
            $blocks->remove($request->user(), $publicId);
        } catch (BookingRefused) {
            abort(404);
        }

        return back()->with('status', __('Block removed.'));
    }

    public function destroyHours(Request $request, string $publicId, CounselorWeeklyHours $hours): RedirectResponse
    {
        try {
            $hours->remove($request->user(), $publicId);
        } catch (BookingRefused) {
            abort(404);
        }

        return back()->with('status', __('Line removed. Times it already opened stay until you remove them.'));
    }

    public function fillHours(Request $request, CounselorWeeklyHours $hours): RedirectResponse
    {
        $input = $request->validate(['weeks' => ['required', 'integer', 'between:1,'.CounselorWeeklyHours::MAX_WEEKS]]);

        try {
            $done = $hours->fill($request->user(), (int) $input['weeks']);
        } catch (BookingRefused $refused) {
            return back()->withErrors(['planner' => $refused->getMessage()]);
        }

        return back()->with('status', __(':created times opened, :skipped skipped (already open, or they would clash with something on your calendar).', $done));
    }
}
