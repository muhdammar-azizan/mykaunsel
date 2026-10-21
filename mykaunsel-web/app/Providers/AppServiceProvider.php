<?php

namespace App\Providers;

use App\Listeners\RecordAuthActivity;
use App\Services\AuditLogger;
use App\Support\AuditContext;
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
        // One request id / system flag per request (or per console run), shared by every audit entry.
        $this->app->singleton(AuditContext::class);
        $this->app->singleton(AuditLogger::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(fn () => Password::min(8)->mixedCase()->numbers()->symbols());

        // Sign-in trail. Best-effort: a failure to write never blocks a sign-in, but it is reported.
        Event::listen(Login::class, [RecordAuthActivity::class, 'onLogin']);
        Event::listen(Failed::class, [RecordAuthActivity::class, 'onFailed']);
    }
}
