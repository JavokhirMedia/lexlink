<?php

namespace App\Http\Controllers\MockGov;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Agency-side API key check for /mock-gov/v1/* (except OneID, which uses OAuth2 credentials). */
class RequireMockGovApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('lexlink.mock_gov.api_key');
        $given = (string) $request->header('X-Api-Key', '');

        if ($expected === '' || ! hash_equals($expected, $given)) {
            return response()->json([
                'error' => 'unauthorized',
                'message' => "API kaliti noto'g'ri yoki ko'rsatilmagan (X-Api-Key).",
            ], 401);
        }

        return $next($request);
    }
}
