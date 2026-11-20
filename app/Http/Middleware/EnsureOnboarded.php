<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Users without a role (fresh OneID login) must finish onboarding first. */
class EnsureOnboarded
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->user()->role === null && ! $request->routeIs('onboarding.*', 'logout')) {
            return redirect()->route('onboarding.show');
        }

        return $next($request);
    }
}
