<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Lets "log out other devices" actually invalidate those sessions after a password change.
        $middleware->authenticateSessions();

        // The one-click opt-out (List-Unsubscribe-Post) is sent by the mail program, which has no CSRF token. The link itself is
        // signed with a secret key, so nobody can opt someone else out without having been sent the link.
        $middleware->validateCsrfTokens(except: ['waitlist/unsubscribe/*']);

        // Every request gets an id (X-Request-Id) that groups the audit entries written during it.
        $middleware->append(\App\Http\Middleware\AssignRequestId::class);

        // Maintenance mode (Registration Control): blocks everyone except a signed-in Platform Admin.
        $middleware->web(append: [\App\Http\Middleware\BlockDuringMaintenance::class]);

        // The mobile app's API: always answers in JSON, and obeys maintenance mode like the web.
        $middleware->api(prepend: [\App\Http\Middleware\ForceJsonResponse::class]);
        $middleware->api(append: [
            \App\Http\Middleware\BlockDuringMaintenance::class,
            \App\Http\Middleware\RequireCurrentAppVersion::class,
        ]);

        // Behind a load balancer / reverse proxy the real visitor IP arrives in a header. Trust it only from the proxies
        // listed in TRUSTED_PROXIES (comma-separated IPs, or * for "whatever proxy is calling us"). Without this every
        // visitor looks like the proxy, so rate limits and the audit log would all see one single IP.
        $middleware->trustProxies(at: env('TRUSTED_PROXIES') ?: null);

        $middleware->alias([
            'registration.open' => \App\Http\Middleware\EnsureRegistrationOpen::class,
            'registration.web-individual' => \App\Http\Middleware\EnsureWebIndividualRegistration::class,
            'org.context' => \App\Http\Middleware\EnsureOrgContext::class,
            'membership.active' => \App\Http\Middleware\CheckMembershipStatus::class,
            'role' => \App\Http\Middleware\RequireRole::class,
            'org.status' => \App\Http\Middleware\CheckOrgStatus::class,
            'legacy.account' => \App\Http\Middleware\LegacyAccountGuard::class,
            'api.verified' => \App\Http\Middleware\ApiEnsureEmailVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Errors on /api/* are JSON (401, 403, 422, 429…), never a redirect or an HTML page.
        // A waitlist form left open until its session expired: back to the page with a friendly line, not a 419 error page.
        // (Laravel has already turned the expired-token error into an HTTP 419 error by the time this runs.)
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, $request) {
            if ($e->getStatusCode() === 419 && $request->routeIs('waitlist.store')) {
                return redirect($request->route('type') === 'counselor' ? route('counselors.signup.create') : route('organizations.signup.create'))
                    ->with('waitlist_error', 'That page was open for too long. Please try again.');
            }
        });

        $exceptions->shouldRenderJsonWhen(fn ($request, $e) => $request->is('api/*') || $request->expectsJson());
    })->create();
