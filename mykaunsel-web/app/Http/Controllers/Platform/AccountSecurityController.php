<?php

namespace App\Http\Controllers\Platform;

use App\Facades\Audit;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\SessionManager;
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

    public function updatePassword(Request $request, SessionManager $sessions): RedirectResponse
    {
        $data = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::defaults()],
        ], [
            'current_password.current_password' => 'That password is not correct.',
            'password.different' => 'Choose a password you have not used just now.',
        ]);

        $user = $request->user();

        // ONE transaction: the new password, the other devices signed out, the app's access tokens ended and the audit entry (never the password).
        DB::transaction(function () use ($user, $data, $request, $sessions) {
            $user->forceFill(['password' => $data['password']])->save();

            // Sign out every OTHER device; this session stays signed in (AuthenticateSession keeps it in sync).
            Auth::logoutOtherDevices($data['password']);
            $sessions->forgetOthers($request);

            // The mobile app signs in with tokens, which a changed password must end too.
            $tokens = $user->tokens()->delete();

            Audit::log('account.password_changed', $user, ['api_tokens_ended' => $tokens]);
        });

        return redirect()->to(\App\Support\AccountArea::route('account.security'))
            ->with('status', 'Password updated. Other devices were signed out.');
    }

    public function revokeOtherSessions(Request $request, SessionManager $sessions): RedirectResponse
    {
        $data = $request->validateWithBag('revokeSessions', [
            'current_password' => ['required', 'current_password'],
        ], ['current_password.current_password' => 'That password is not correct.']);

        $count = $sessions->countOthers($request);
        $tokens = 0;

        // ONE transaction: the other sessions, the app's access tokens and the audit entry.
        DB::transaction(function () use ($request, $data, $sessions, &$tokens, $count) {
            Auth::logoutOtherDevices($data['current_password']);
            $sessions->forgetOthers($request);

            // "Sign out my other devices" includes the phone: its tokens end as well.
            $tokens = $request->user()->tokens()->delete();

            Audit::log('account.other_sessions_revoked', $request->user(), ['sessions' => $count, 'api_tokens_ended' => $tokens]);
        });

        $status = match (true) {
            $count > 0 && $tokens > 0 => "Signed out {$count} other ".str('session')->plural($count).' and the app.',
            $count > 0 => "Signed out {$count} other ".str('session')->plural($count).'.',
            $tokens > 0 => 'Signed out the app.',
            default => 'There were no other sessions.',
        };

        return redirect()->to(\App\Support\AccountArea::route('account.security'))->with('status', $status);
    }
}
