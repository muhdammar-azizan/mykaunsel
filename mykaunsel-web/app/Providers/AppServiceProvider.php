<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(fn () => Password::min(8)->mixedCase()->numbers()->symbols());

        // Security trail for "Recent security activity". Never includes credentials, and a
        // failure to write the log must never block a sign-in.
        Event::listen(Login::class, function (Login $event) {
            if ($event->user instanceof User) {
                rescue(fn () => AuditLog::record('login', $event->user, [], $event->user), report: true);
            }
        });

        Event::listen(Failed::class, function (Failed $event) {
            if ($event->user instanceof User) {
                rescue(fn () => AuditLog::record('login_failed', $event->user, [], $event->user), report: true);
            }
        });
    }
}
