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
        // What they already have from the list (not the ones they wrote themselves, which are checked below).
        $currentListed = $profile->specializations()->where('is_custom', false)->pluck('specialization')->all();
        $taken = collect($listed('specializations', $currentListed))->map(fn ($s) => mb_strtolower($s))->all();

        // Their own: spaces tidied, the first letter in capitals, the same one only once.
        $request->merge(['custom_specializations' => collect((array) $request->input('custom_specializations', []))
            ->filter(fn ($v) => is_string($v))
            ->map(fn (string $v) => mb_strtoupper(mb_substr($v = trim((string) preg_replace('/\s+/u', ' ', $v)), 0, 1)).mb_substr($v, 1))
            ->filter(fn (string $v) => $v !== '')
            ->unique(fn (string $v) => mb_strtolower($v))
            ->values()->all()]);

        $input = $request->validate([
            'display_name_org' => ['nullable', 'string', 'max:100'],
            'display_name_independent' => ['nullable', 'string', 'max:100'],
            'languages' => ['nullable', 'array', 'max:20'],
            'languages.*' => ['string', Rule::in($listed('languages', $profile->languages()->pluck('language')->all()))],
            'specializations' => ['nullable', 'array', 'max:20'],
            'specializations.*' => ['string', Rule::in($listed('specializations', $currentListed))],
            'custom_specializations' => ['array', 'max:'.CounselorProfileSettings::CUSTOM_MAX],
            'custom_specializations.*' => [
                'string', 'max:'.CounselorProfileSettings::CUSTOM_MAX_CHARS, 'regex:/^\p{L}[\p{L} &\'\/-]*$/u',
                fn ($attribute, $value, $fail) => count(explode(' ', $value)) > CounselorProfileSettings::CUSTOM_MAX_WORDS
                    ? $fail('Use at most '.CounselorProfileSettings::CUSTOM_MAX_WORDS.' words.') : null,
                fn ($attribute, $value, $fail) => in_array(mb_strtolower($value), $taken, true) ? $fail('That one is already in the list above. Tick it there.') : null,
            ],
            'meeting_provider' => ['nullable', Rule::in(CounselorSessions::PROVIDERS)],
            'buffer_minutes' => ['required', 'integer', Rule::in(config('counselors.profile.buffer_minutes'))],
        ]);
        $input['accepts_requests'] = $request->boolean('accepts_requests');
        // Something both ticked in the list and typed as their own is kept as the list one.
        $input['custom_specializations'] = collect($input['custom_specializations'] ?? [])->reject(fn ($v) => in_array(mb_strtolower($v), $taken, true))->values()->all();

        $changed = $settings->update($request->user(), $input);

        return back()->with('status', $changed === [] ? __('Nothing changed.') : __('Profile saved.'));
    }
}
