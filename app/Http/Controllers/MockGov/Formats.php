<?php

namespace App\Http\Controllers\MockGov;

use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;

/** Shared agency response helpers. */
trait Formats
{
    /** ISO-8601 in Tashkent time (+05:00). */
    protected function ts(?CarbonInterface $at = null): string
    {
        return ($at ?? now())->copy()->setTimezone('Asia/Tashkent')->toIso8601String();
    }

    protected function notFound(string $message): JsonResponse
    {
        return response()->json(['error' => 'not_found', 'message' => $message], 404);
    }
}
