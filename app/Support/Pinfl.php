<?php

namespace App\Support;

/** JShShIR (PINFL) helpers. */
final class Pinfl
{
    public static function hash(string $pinfl): string
    {
        return hash_hmac('sha256', $pinfl, (string) config('app.key'));
    }

    /** 31234567890112 -> 3**********12 (spec: 3*********12) */
    public static function mask(?string $pinfl): ?string
    {
        if ($pinfl === null || strlen($pinfl) < 4) {
            return $pinfl;
        }

        return substr($pinfl, 0, 1).str_repeat('*', strlen($pinfl) - 3).substr($pinfl, -2);
    }

    public static function isValid(string $pinfl): bool
    {
        return (bool) preg_match('/^\d{14}$/', $pinfl);
    }
}
