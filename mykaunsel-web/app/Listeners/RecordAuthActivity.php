<?php

namespace App\Listeners;

use App\Facades\Audit;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Sign-in trail. Best-effort by design: if writing the log fails the sign-in still succeeds,
 * but the failure is reported (never silent).
 *
 * Never stores what was typed: no password, and for an unknown email only a keyed hash
 * (people sometimes type a password into the email field).
 */
class RecordAuthActivity
{
    // Registered by hand in AppServiceProvider. The methods are deliberately not named handle*(),
    // which would make Laravel's listener auto-discovery register them a second time.

    public function onLogin(Login $event): void
    {
        if ($event->user instanceof User) {
            Audit::tryLog('auth.login', $event->user, [], $event->user);
        }
    }

    public function onFailed(Failed $event): void
    {
        $ip = request()->ip() ?? 'cli';

        // A password-guessing run must not flood the table: past the limit, attempts are only counted.
        $allowed = RateLimiter::attempt(
            'audit-login-failed:'.$ip,
            (int) config('audit.login_failed_per_ip_per_minute', 10),
            fn () => true,
            60,
        );

        if (! $allowed) {
            Cache::add('audit:login_failed_suppressed', 0, now()->addDay());
            Cache::increment('audit:login_failed_suppressed');

            return;
        }

        if ($event->user instanceof User) {
            Audit::tryLog('auth.login_failed', $event->user, [], $event->user);

            return;
        }

        $email = strtolower(trim((string) ($event->credentials['email'] ?? '')));

        Audit::tryLog('auth.login_failed', null, [
            'email_hash' => hash_hmac('sha256', $email, (string) config('app.key')),
        ]);
    }
}
