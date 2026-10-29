<?php

namespace App\Providers;

use App\Listeners\RecordAuthActivity;
use App\Services\AuditLogger;
use App\Support\AuditContext;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One request id / system flag per request (or per console run), shared by every audit entry.
        $this->app->singleton(AuditContext::class);
        $this->app->singleton(AuditLogger::class);
        $this->app->singleton(\App\Services\PlatformSettings::class);
        // The in-app notification types: the core ones here; a later page registers its own on this same registry.
        $this->app->singleton(\App\Support\Inbox\NotificationRegistry::class, function () {
            $registry = new \App\Support\Inbox\NotificationRegistry;
            \App\Support\Inbox\CoreNotificationTypes::register($registry);

            return $registry;
        });
        $this->app->singleton(\App\Services\NotificationCenter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(fn () => Password::min(8)->mixedCase()->numbers()->symbols());

        $this->apiRateLimits();

        // Sign-in trail. Best-effort: a failure to write never blocks a sign-in, but it is reported.
        Event::listen(Login::class, [RecordAuthActivity::class, 'onLogin']);
        Event::listen(Failed::class, [RecordAuthActivity::class, 'onFailed']);
    }

    /**
     * Rate limits for the mobile app's API.
     *
     * Mobile networks in Malaysia put many phones behind ONE public IP, so a low per-IP limit would lock out innocent
     * people. The tight limits therefore follow the thing being attacked (an email address, a challenge, a signed-in
     * person); the per-IP limits are only a generous ceiling against floods.
     */
    private function apiRateLimits(): void
    {
        $email = fn (Request $request) => Str::lower(Str::limit(trim((string) $request->input('email')), 255, ''));

        RateLimiter::for('api-public', fn (Request $request) => Limit::perMinute(600)->by('ip|'.$request->ip()));

        RateLimiter::for('api-login', fn (Request $request) => [
            Limit::perMinute(10)->by('login|'.($email($request) ?: $request->ip())),
            Limit::perMinute(300)->by('login-ip|'.$request->ip()),
        ]);

        RateLimiter::for('api-two-factor', fn (Request $request) => [
            Limit::perMinute(10)->by('2fa|'.hash('sha256', (string) $request->input('challenge'))),
            Limit::perMinute(300)->by('2fa-ip|'.$request->ip()),
        ]);

        RateLimiter::for('api-register', fn (Request $request) => [
            Limit::perMinute(60)->by('register-ip|'.$request->ip()),
            Limit::perHour(600)->by('register-ip-hour|'.$request->ip()),
        ]);

        // Emails (confirmation, password reset): few per address, so nobody can flood one person's inbox.
        RateLimiter::for('api-email', fn (Request $request) => [
            Limit::perMinutes(10, 3)->by('mail|'.($email($request) ?: $request->ip())),
            Limit::perMinute(120)->by('mail-ip|'.$request->ip()),
        ]);

        // The counselor directory is the most scrape-worthy thing in the app: tight per person, on top of the sign-in.
        RateLimiter::for('api-directory', fn (Request $request) => [
            Limit::perMinute(40)->by('directory|'.($request->user()?->getAuthIdentifier() ?? $request->ip())),
            Limit::perHour(600)->by('directory-hour|'.($request->user()?->getAuthIdentifier() ?? $request->ip())),
        ]);

        // Anything that checks the current password: a stolen token must not become a way to guess it.
        RateLimiter::for('api-sensitive', fn (Request $request) => [
            Limit::perMinute(5)->by('sensitive|'.($request->user()?->getAuthIdentifier() ?? $request->ip())),
            Limit::perHour(20)->by('sensitive-hour|'.($request->user()?->getAuthIdentifier() ?? $request->ip())),
        ]);

        RateLimiter::for('api-user', fn (Request $request) => Limit::perMinute(120)->by('user|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}
