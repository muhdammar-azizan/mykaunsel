<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Lets "log out other devices" actually invalidate those sessions after a password change.
        $middleware->authenticateSessions();

        $middleware->alias([
            'org.context' => \App\Http\Middleware\EnsureOrgContext::class,
            'membership.active' => \App\Http\Middleware\CheckMembershipStatus::class,
            'role' => \App\Http\Middleware\RequireRole::class,
            'org.status' => \App\Http\Middleware\CheckOrgStatus::class,
            'legacy.account' => \App\Http\Middleware\LegacyAccountGuard::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
