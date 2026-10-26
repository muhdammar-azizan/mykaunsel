<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\PlatformSettings;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Platform Admin → Registration Control: close sign-ups (per kind of account or all), maintenance mode and their wording. */
class PlatformRegistrationController extends Controller
{
    /** What a datetime-local field sends: the admin's own wall-clock time, no zone. */
    private const LOCAL_FORMAT = 'Y-m-d\TH:i';

    public function index(Request $request, PlatformSettings $settings): View
    {
        $status = $settings->registrationStatus();
        $timezone = $request->user()->display_timezone;
        $now = now($timezone);

        return view('platform.registration.index', [
            'roles' => config('platform-settings.registration_roles'),
            'status' => $status,
            'maintenance' => $settings->maintenanceOn(),
            'reopenAt' => $settings->maintenanceReopenAt()?->setTimezone($timezone),
            'reopenOverdue' => $settings->reopenOverdue(),
            'nowLocal' => $now->format(self::LOCAL_FORMAT),
            'timezoneLabel' => $timezone.', UTC'.$now->format('P'),
            'maxDays' => (int) config('platform-settings.maintenance_max_days'),
            'registrationMessage' => $settings->message('registration_closed'),
            'maintenanceMessage' => $settings->message('maintenance'),
            'defaults' => config('platform-settings.default_messages'),
            'max' => config('platform-settings.message_max_length'),
            'previews' => collect(array_keys($status))->mapWithKeys(fn ($role) => [$role => $settings->registrationClosedMessage($role)])->all(),
            'allPreview' => $settings->registrationClosedMessage(),
        ]);
    }

    /** Open or close one kind of sign-up. The page sends the state it wants, so pressing it twice never flips it back. */
    public function toggle(Request $request, string $role, PlatformSettings $settings): RedirectResponse
    {
        abort_unless(in_array($role, $settings->roles(), true), 404);

        $open = $this->wantedState($request);
        $label = config("platform-settings.registration_roles.{$role}.label");

        if ($settings->setRegistration($request->user(), [$role], $open) === []) {
            return $this->back()->with('error', "{$label} sign-up was already ".($open ? 'open' : 'closed').'. Nothing was changed.');
        }

        return $this->back()->with('status', "{$label} sign-up is now ".($open ? 'open.' : 'closed.'));
    }

    /** Open or close every kind of sign-up at once. */
    public function toggleAll(Request $request, PlatformSettings $settings): RedirectResponse
    {
        $open = $this->wantedState($request);

        $changed = $settings->setRegistration($request->user(), $settings->roles(), $open, forAll: true);

        if ($changed === []) {
            return $this->back()->with('error', 'Every sign-up was already '.($open ? 'open' : 'closed').'. Nothing was changed.');
        }

        return $this->back()->with('status', 'Every sign-up is now '.($open ? 'open.' : 'closed.'));
    }

    /** Turn maintenance on (optionally with the time it switches itself off) or off. */
    public function maintenance(Request $request, PlatformSettings $settings): RedirectResponse
    {
        $on = $request->validate(['state' => ['required', Rule::in(['on', 'off'])]])['state'] === 'on';

        // Checked first, so a bad time never turns maintenance on half-way.
        $reopenAt = $on ? $this->reopenTime($request) : null;

        if (! $settings->setMaintenance($request->user(), $on, reopenAt: $reopenAt)) {
            return $this->back()->with('error', 'Maintenance mode was already '.($on ? 'on' : 'off').'. Nothing was changed.');
        }

        return $this->back()->with('status', $on
            ? 'Maintenance mode is ON. Everyone except Platform Admins is blocked.'.($reopenAt ? ' It switches off by itself at '.$this->show($request, $reopenAt).'.' : '')
            : 'Maintenance mode is off. MyKaunsel is back for everyone.');
    }

    /** Set, change or remove (empty) the time maintenance switches itself off. */
    public function reopenTimeUpdate(Request $request, PlatformSettings $settings): RedirectResponse
    {
        $reopenAt = $this->reopenTime($request);

        if (! $settings->maintenanceOn()) {
            return $this->back()->with('error', 'Maintenance mode is off, so there is no reopen time to change.');
        }

        if (! $settings->setMaintenanceReopenAt($request->user(), $reopenAt)) {
            return $this->back()->with('error', 'That is already the reopen time. Nothing was changed.');
        }

        return $this->back()->with('status', $reopenAt
            ? 'Maintenance switches off by itself at '.$this->show($request, $reopenAt).'.'
            : 'Reopen time removed. Maintenance stays on until you turn it off.');
    }

    public function messages(Request $request, PlatformSettings $settings): RedirectResponse
    {
        $max = (int) config('platform-settings.message_max_length');

        $data = $request->validateWithBag('messages', [
            'registration_closed' => ['required', 'string', 'min:10', "max:{$max}"],
            'maintenance' => ['required', 'string', 'min:10', "max:{$max}"],
        ], [
            'registration_closed.required' => 'Write the message people see when sign-up is closed.',
            'maintenance.required' => 'Write the message people see during maintenance.',
            'registration_closed.min' => 'Give a little more detail (at least 10 characters).',
            'maintenance.min' => 'Give a little more detail (at least 10 characters).',
        ]);

        $changed = $settings->updateMessages($request->user(), $data);

        return $this->back()->with('status', $changed === [] ? 'Nothing changed.' : 'Messages saved.');
    }

    private function wantedState(Request $request): bool
    {
        return $request->validate(['state' => ['required', Rule::in(['open', 'closed'])]])['state'] === 'open';
    }

    /**
     * The reopen time from the form: empty = none. The admin types their own wall-clock time, read in THEIR timezone and
     * kept as an instant. It must be at least a minute away and not more than `maintenance_max_days` ahead.
     */
    private function reopenTime(Request $request): ?CarbonImmutable
    {
        $timezone = $request->user()->display_timezone;
        $maxDays = (int) config('platform-settings.maintenance_max_days');
        $parsed = null;

        Validator::make($request->only('reopen_at'), ['reopen_at' => ['nullable', 'date_format:'.self::LOCAL_FORMAT]], [
            'reopen_at.date_format' => 'Choose the reopen time with the date and time picker.',
        ])->after(function ($validator) use ($request, $timezone, $maxDays, &$parsed) {
            $value = $request->input('reopen_at');

            if ($validator->errors()->has('reopen_at') || blank($value)) {
                return;
            }

            $parsed = CarbonImmutable::createFromFormat(self::LOCAL_FORMAT, $value, $timezone)->utc();

            if ($parsed->lte(now()->addMinute())) {
                $validator->errors()->add('reopen_at', "Choose a time at least a minute from now (your time zone is {$timezone}).");
            } elseif ($parsed->gt(now()->addDays($maxDays))) {
                $validator->errors()->add('reopen_at', "Choose a time no more than {$maxDays} days from now. Maintenance can always be switched off by hand.");
            }
        })->validateWithBag('maintenance');

        return $parsed;
    }

    private function show(Request $request, CarbonImmutable $at): string
    {
        return $at->setTimezone($request->user()->display_timezone)->format('j M Y, g:i A');
    }

    private function back(): RedirectResponse
    {
        return redirect()->route('platform.registration');
    }
}
