<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\TwoFactorAuthenticator;
use App\Support\UserAgent;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AccountSecurityController extends Controller
{
    public function show(Request $request, TwoFactorAuthenticator $twoFactor): View
    {
        $user = $request->user();
        $timezone = $user->display_timezone;
        $driverIsDatabase = config('session.driver') === 'database';

        $sessions = collect();
        if ($driverIsDatabase) {
            $currentId = $request->session()->getId();

            // Scoped to the signed-in user's id only — never anyone else's sessions.
            $sessions = DB::table('sessions')
                ->where('user_id', $user->id)
                ->orderByDesc('last_activity')
                ->get()
                ->map(function ($row) use ($currentId, $timezone) {
                    $agent = UserAgent::parse($row->user_agent);
                    $last = Carbon::createFromTimestamp($row->last_activity, $timezone);

                    return $agent + [
                        'ip' => $row->ip_address,
                        'current' => $row->id === $currentId,
                        'last_active' => $last->diffForHumans(),
                        'last_active_exact' => $last->format('j M Y, g:i A'),
                    ];
                });
        }

        $events = config('account.security_events');
        $activity = AuditLog::where('actor_user_id', $user->id)
            ->whereIn('action', array_keys($events))
            ->latest('created_at')->latest('id')
            ->limit(10)
            ->get()
            ->map(fn ($log) => [
                'label' => $events[$log->action],
                'action' => $log->action,
                'ip' => $log->ip,
                'at' => $log->created_at->timezone($timezone)->format('j M Y, g:i A'),
                'ago' => $log->created_at->diffForHumans(),
            ]);

        $secret = $user->two_factor_secret;
        $twoFactorState = [
            'enabled' => $user->hasTwoFactorEnabled(),
            'pending' => $user->hasPendingTwoFactor(),
            'enabled_at' => $user->two_factor_confirmed_at?->timezone($timezone)->format('j M Y'),
            'remaining_codes' => count($user->recoveryCodeHashes()),
            'recovery_codes' => session('recovery_codes'),
            'qr' => null,
            'manual_key' => null,
        ];

        if ($user->hasPendingTwoFactor()) {
            $twoFactorState['qr'] = $twoFactor->qrSvg($twoFactor->otpauthUrl($user, $secret));
            $twoFactorState['manual_key'] = $twoFactor->manualKey($secret);
        }

        return view('platform.account.security', [
            'twoFactor' => $twoFactorState,
            'user' => $user,
            'sessions' => $sessions,
            'driverIsDatabase' => $driverIsDatabase,
            'activity' => $activity,
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::defaults()],
        ], [
            'current_password.current_password' => 'That password is not correct.',
            'password.different' => 'Choose a password you have not used just now.',
        ]);

        $user = $request->user();
        $user->forceFill(['password' => $data['password']])->save();

        // Sign out every OTHER device; this session stays signed in (AuthenticateSession keeps it in sync).
        Auth::logoutOtherDevices($data['password']);
        $this->forgetOtherSessions($request);

        AuditLog::record('password_changed', $user);

        return redirect()->route('platform.account.security')
            ->with('status', 'Password updated. Other devices were signed out.');
    }

    public function revokeOtherSessions(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('revokeSessions', [
            'current_password' => ['required', 'current_password'],
        ], ['current_password.current_password' => 'That password is not correct.']);

        $count = $this->otherSessionsQuery($request)->count();

        Auth::logoutOtherDevices($data['current_password']);
        $this->forgetOtherSessions($request);

        AuditLog::record('other_sessions_revoked', $request->user(), ['sessions' => $count]);

        return redirect()->route('platform.account.security')
            ->with('status', $count ? "Signed out {$count} other ".str('session')->plural($count).'.' : 'There were no other sessions.');
    }

    private function otherSessionsQuery(Request $request)
    {
        return DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $request->session()->getId());
    }

    /** Remove stored session rows right away so the list is correct without waiting for those devices to make a request. */
    private function forgetOtherSessions(Request $request): void
    {
        if (config('session.driver') === 'database') {
            $this->otherSessionsQuery($request)->delete();
        }
    }
}
