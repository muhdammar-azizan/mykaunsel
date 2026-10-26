<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Facades\Audit;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Signing in from the mobile app. Same rules as the web sign-in: rate-limited, the audit trail for success and failure,
 * no token until the password is right AND the email address is confirmed, and a second step for accounts with
 * two-factor on (a password alone never gives a token to such an account).
 */
class ApiAuthService
{
    private const MAX_PASSWORD_ATTEMPTS = 5;

    private const MAX_CODE_ATTEMPTS = 5;

    private const CHALLENGE_MINUTES = 10;

    /** Tokens kept per person (one per device, roughly): the oldest are removed beyond this. */
    private const MAX_TOKENS = 10;

    public function __construct(private TwoFactorAuthenticator $twoFactor) {}

    /**
     * Password step.
     *
     * @return array{user: User}|array{challenge: string}  a challenge id means a code from the authenticator app is needed next
     *
     * @throws ValidationException wrong credentials / too many attempts
     * @throws ApiException email not confirmed
     */
    public function attempt(Request $request, string $email, string $password): array
    {
        $key = 'api-login|'.Str::lower($email).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_PASSWORD_ATTEMPTS)) {
            event(new Lockout($request));

            throw ValidationException::withMessages(['email' => trans('auth.throttle', [
                'seconds' => $seconds = RateLimiter::availableIn($key),
                'minutes' => ceil($seconds / 60),
            ])]);
        }

        $user = User::query()->where('email', Str::lower(trim($email)))->first();

        // An unknown email costs the same time as a wrong password, so the two cannot be told apart.
        if ($user === null) {
            Hash::make(Str::random(16));
            $ok = false;
        } else {
            $ok = Hash::check($password, $user->password);
        }

        if (! $ok) {
            RateLimiter::hit($key);
            event(new Failed('sanctum', $user, ['email' => $email]));

            throw ValidationException::withMessages(['email' => trans('auth.failed')]);
        }

        RateLimiter::clear($key);

        if (! $user->hasVerifiedEmail()) {
            throw new ApiException('Confirm your email address first. We sent you a link.', 'email_not_verified', 403);
        }

        if ($user->hasTwoFactorEnabled()) {
            $challenge = Str::random(48);
            Cache::put($this->challengeKey($challenge), $user->id, now()->addMinutes(self::CHALLENGE_MINUTES));

            return ['challenge' => $challenge];
        }

        return ['user' => $user];
    }

    /**
     * Second step: the 6-digit code (or a recovery code) for the challenge from attempt().
     *
     * @throws ValidationException wrong / expired / too many attempts
     */
    public function completeTwoFactor(Request $request, string $challenge, ?string $code, ?string $recoveryCode): User
    {
        $userId = Cache::get($this->challengeKey($challenge));
        $user = $userId ? User::find($userId) : null;

        if (! $user || ! $user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages(['challenge' => 'Your sign-in expired. Please sign in again.']);
        }

        // Same budget as the web second step, so the two cannot be used to double the guesses.
        $key = 'two-factor|'.$user->id.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_CODE_ATTEMPTS)) {
            Cache::forget($this->challengeKey($challenge));

            throw ValidationException::withMessages(['code' => 'Too many attempts. Sign in again in '.RateLimiter::availableIn($key).' seconds.']);
        }

        $usingRecovery = filled($recoveryCode);
        $valid = $usingRecovery
            ? $this->twoFactor->consumeRecoveryCode($user, $recoveryCode)
            : $this->twoFactor->verify($user, $code);

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
        Cache::forget($this->challengeKey($challenge));

        if ($usingRecovery) {
            Audit::log('account.recovery_code_used', $user, ['remaining' => count($user->recoveryCodeHashes())], $user);
        }

        return $user;
    }

    /** Sign the person in on this device: record it, and hand out a token (the only time its text is shown). */
    public function issueToken(Request $request, User $user, string $deviceName): string
    {
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->saveQuietly();

        event(new Login('sanctum', $user, false)); // the audit trail ("Signed in") listens to this

        // Make room: keep the newest tokens only.
        $old = $user->tokens()->orderByDesc('id')->pluck('id')->slice(self::MAX_TOKENS - 1);
        if ($old->isNotEmpty()) {
            $user->tokens()->whereIn('id', $old)->delete();
        }

        return $user->createToken(Str::limit($deviceName, 100, ''))->plainTextToken;
    }

    /** @return array<string, mixed> what the app may know about the signed-in person */
    public function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => $user->hasVerifiedEmail(),
            'avatar_url' => $user->avatar_url,
            'timezone' => $user->display_timezone,
        ];
    }

    private function challengeKey(string $challenge): string
    {
        return 'api-2fa:'.hash('sha256', $challenge);
    }
}
