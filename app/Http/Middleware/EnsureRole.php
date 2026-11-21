<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Usage: ->middleware('role:advocate') or 'role:advocate,admin' */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless($request->user() && in_array($request->user()->role?->value, $roles, true), 403, 'Bu amal sizning rolingiz uchun ruxsat etilmagan.');

        return $next($request);
    }
}
