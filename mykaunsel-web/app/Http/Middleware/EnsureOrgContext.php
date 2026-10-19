<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrgContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has('current_org_id') || ! $request->session()->has('current_role')) {
            return redirect()->route('context.select');
        }

        return $next($request);
    }
}
