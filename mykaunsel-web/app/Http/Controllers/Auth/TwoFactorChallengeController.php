<?php

namespace App\Http\Controllers\Auth;

use App\Facades\Audit;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactorAuthenticator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Second step of sign-in for accounts with two-factor on. The user is NOT signed in yet:
 * after the password check only a short-lived "pending" marker lives in the session.
 */
class TwoFactorChallengeController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const PENDING_MINUTES = 10;

    public function __construct(private TwoFactorAuthenticator $twoFactor) {}

    public function create(Request $request): View|RedirectResponse
    {
        if (! $this->pendingUser($request)) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login')->withErrors(['email' => 'Your sign-in expired. Please sign in again.']);
        }

        $request->validate([
            'code' => ['nullable', 'string', 'max:32'],
            'recovery_code' => ['nullable', 'string', 'max:32'],
        ]);

        if (! $request->filled('code') && ! $request->filled('recovery_code')) {
            throw ValidationException::withMessages(['code' => 'Enter the 6-digit code from your authenticator app.']);
        }

        $key = 'two-factor|'.$user->id.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages(['code' => "Too many attempts. Try again in {$seconds} seconds."]);
        }

        $usingRecovery = $request->filled('recovery_code');
        $valid = $usingRecovery
            ? $this->twoFactor->consumeRecoveryCode($user, $request->input('recovery_code'))
            : $this->twoFactor->verify($user, $request->input('code'));

        if (! $valid) {
            RateLimiter::hit($key, 60);
            Audit::log('account.two_factor_failed', $user, [], $user);

            throw ValidationException::withMessages([
                $usingRecovery ? 'recovery_code' : 'code' => $usingRecovery
                    ? 'That recovery code is not valid or was already used.'
                    : 'That code is not correct. Try the newest code from your app.',
            ]);
        }

        RateLimiter::clear($key);
        $remember = (bool) $request->session()->get('two_factor.remember', false);
        $request->session()->forget('two_factor');

        Auth::login($user, $remember);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->saveQuietly();

        if ($usingRecovery) {
            Audit::log('account.recovery_code_used', $user, ['remaining' => count($user->recoveryCodeHashes())], $user);
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /** The user who passed the password step in the last few minutes, if any. */
    private function pendingUser(Request $request): ?User
    {
        $state = $request->session()->get('two_factor');

        if (! is_array($state) || ($state['expires'] ?? 0) < now()->timestamp) {
            $request->session()->forget('two_factor');

            return null;
        }

        $user = User::find($state['id'] ?? null);

        return $user && $user->hasTwoFactorEnabled() ? $user : null;
    }

    public static function begin(Request $request, User $user): void
    {
        $request->session()->put('two_factor', [
            'id' => $user->id,
            'remember' => $request->boolean('remember'),
            'expires' => now()->addMinutes(self::PENDING_MINUTES)->timestamp,
        ]);
    }
}
