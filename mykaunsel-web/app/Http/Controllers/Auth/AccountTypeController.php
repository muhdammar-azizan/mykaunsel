<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PlatformSettings;
use App\Services\WaitlistService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class AccountTypeController extends Controller
{
    public function index(Request $request, PlatformSettings $settings, WaitlistService $waitlist): View|Response
    {
        // Only the two kinds of account that sign up on the web are offered here.
        $open = [
            'organization' => $settings->registrationOpen('organization'),
            'counselor' => $settings->registrationOpen('counselor'),
        ];

        // The kinds that are closed and have a waitlist offer it (never individuals).
        $waitlistTypes = array_values(array_filter(array_keys($open), fn ($role) => $waitlist->offered($role)));

        // Never cached: the page carries the waitlist form and its CSRF token.
        $headers = ['Cache-Control' => 'no-store, private'];

        if (! in_array(true, $open, true)) {
            return response()->view('registration-closed', ['message' => $settings->registrationClosedMessage(), 'waitlistTypes' => $waitlistTypes], 403, $headers + ['X-Robots-Tag' => 'noindex, nofollow']);
        }

        return response()->view('auth.select-account-type', [
            'open' => $open,
            'closedMessages' => collect($open)->reject()->map(fn ($v, $role) => $settings->registrationClosedMessage($role))->all(),
            'waitlistTypes' => $waitlistTypes,
        ], 200, $headers);
    }
}
