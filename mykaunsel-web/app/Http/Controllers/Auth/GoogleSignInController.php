<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * "Continue with Google" on the login page. It only SIGNS IN an account that already exists: the Google e-mail must be verified by Google
 * and match a MyKaunsel account's e-mail. It never creates an account, so organizations and counselors can not skip their approval.
 * Accounts with two-factor on still go through the code step.
 */
class GoogleSignInController extends Controller
{
    public function redirect(): RedirectResponse
    {
        if (! $this->configured()) {
            return redirect()->route('login')->withErrors(['email' => 'Google sign in is not set up yet.']);
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! $this->configured() || $request->has('error')) {
            return $this->back('Google sign in was cancelled or is not available. Please try again.');
        }

        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            report($e);

            return $this->back('Google sign in failed. Please try again.');
        }

        $verified = ($google->user['email_verified'] ?? false) === true;
        $user = $verified && $google->getEmail() ? User::where('email', $google->getEmail())->first() : null;

        if (! $user) {
            return $this->back('No MyKaunsel account uses that Google e-mail. Sign in with your e-mail and password instead.');
        }

        if ($user->hasTwoFactorEnabled()) {
            TwoFactorChallengeController::begin($request, $user);

            return redirect()->route('two-factor.challenge');
        }

        Auth::login($user);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->saveQuietly();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function configured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    private function back(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
