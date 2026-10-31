<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    /** `role:counselor` or, for pages both portals share, `role:org_admin,counselor`. */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! in_array($request->session()->get('current_role'), $roles, true)) {
            abort(403);
        }

        return $next($request);
    }
}
