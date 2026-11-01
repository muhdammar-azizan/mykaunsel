<?php

namespace App\Http\Controllers\Counselor;

use App\Http\Controllers\Controller;
use App\Services\CounselorProfileSettings;
use App\Services\CounselorSessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The counselor's own Profile page: verification status (read only) and what they may change about how clients see and book them. */
class CounselorProfileController extends Controller
{
    public function index(Request $request, CounselorProfileSettings $settings)
    {
        abort_if($request->user()->counselorProfile === null, 404);

        return view('counselors.dashboard.profile', ['p' => $settings->for($request->user())]);
    }

    public function update(Request $request, CounselorProfileSettings $settings): RedirectResponse
    {
        abort_if($request->user()->counselorProfile === null, 404);

        $listed = fn (string $key, array $current) => collect(config("counselors.profile.{$key}"))->merge($current)->unique()->values()->all();
        $profile = $request->user()->counselorProfile;

        $input = $request->validate([
            'display_name_org' => ['nullable', 'string', 'max:100'],
            'display_name_independent' => ['nullable', 'string', 'max:100'],
            'languages' => ['nullable', 'array', 'max:20'],
            'languages.*' => ['string', Rule::in($listed('languages', $profile->languages()->pluck('language')->all()))],
            'specializations' => ['nullable', 'array', 'max:20'],
            'specializations.*' => ['string', Rule::in($listed('specializations', $profile->specializations()->pluck('specialization')->all()))],
            'meeting_provider' => ['nullable', Rule::in(CounselorSessions::PROVIDERS)],
            'buffer_minutes' => ['required', 'integer', Rule::in(config('counselors.profile.buffer_minutes'))],
        ]);
        $input['accepts_requests'] = $request->boolean('accepts_requests');

        $changed = $settings->update($request->user(), $input);

        return back()->with('status', $changed === [] ? __('Nothing changed.') : __('Profile saved.'));
    }
}
