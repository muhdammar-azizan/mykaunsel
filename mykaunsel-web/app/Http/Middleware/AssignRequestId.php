<?php

namespace App\Http\Middleware;

use App\Support\AuditContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Gives every request its own id so audit entries written during it can be grouped. Never trusts a client-sent id. */
class AssignRequestId
{
    public function __construct(private AuditContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $id = $this->context->startRequest();

        $response = $next($request);
        $response->headers->set('X-Request-Id', $id);

        return $response;
    }
}
