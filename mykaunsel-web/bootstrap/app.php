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
        $middleware->alias([
            'org.context' => \App\Http\Middleware\EnsureOrgContext::class,
            'membership.active' => \App\Http\Middleware\CheckMembershipStatus::class,
            'role' => \App\Http\Middleware\RequireRole::class,
            'org.status' => \App\Http\Middleware\CheckOrgStatus::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
