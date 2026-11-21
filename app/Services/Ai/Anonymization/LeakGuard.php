<?php

namespace App\Services\Ai\Anonymization;

use App\Services\Ai\PiiMasker;

/**
 * Final leak check on the exact text that would leave the server.
 * Two layers: (1) the "known PII" set of the context (names, PINFLs, account / plate / cadastral numbers,
 * organisation names, case numbers ... registered from the case, the request, the response payload and the users);
 * (2) context-free regex detectors. Matching is case-insensitive and apostrophe-variant aware (o' oʻ o‘ o’).
 * Returns category => count only; values are never returned, logged or audited.
 */
final class LeakGuard
{
    /** Context-free detectors: category => regex (run on the original text). */
    private const DETECTORS = [
        'address' => PiiMasker::ADDRESS_RE,
        'organization' => PiiMasker::ORG_RE,
        'passport' => '/(?<![A-Z\d])[A-Z]{2}\s?\d{7}(?!\d)/u',
        'phone' => '/\+\s?998|(?<![\d\w])\(?\d{2}\)?[\s\-]\d{3}[\s\-]\d{2}[\s\-]\d{2}(?!\d)/u',
        'plate' => '/(?<![A-Z\d])(?:\d{2}\s?[A-Z]\s?\d{3}\s?[A-Z]{2}|\d{2}\s?\d{3}\s?[A-Z]{3})(?![A-Z\d])/u',
        'cadastral' => '/(?<!\d)\d{2}:\d{2}:\d{2}(?::\d{1,5}){1,}/u',
        'email' => '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/u',
        // birth dates only — ordinary event dates (periods, crossings, decisions) may be sent
        'birth_date' => "/(?:tug['ʻʼ’‘`]ilgan(?:\\s+(?:sana(?:si)?|kuni|yili))?|t\\.\\s?y\\.|birth(?:\\s?date)?|date of birth|dob)[\\s:\\-–—\"]{0,6}(?:\\d{4}-\\d{2}-\\d{2}|\\d{2}\\.\\d{2}\\.\\d{4})/iu",
        'case_number' => '/(?<!\d)\d{1,2}-\d{3,4}-\d{4}\/\d{2,5}/u',
        'decision_number' => '/\bORD-\d{4}-\d+/u',
        'request_number' => '/\bGOV-\d{4}-\d+/u',
        'license' => '/\b[A-Z]{2}-\d{4,6}\b/u',
        'tin' => '/\b(?:STIR|INN|TIN)\b[\s:№#\-]{0,4}\d{9}(?!\d)/iu',
        'masked_id' => '/(?<![\d*])\d{1,8}\*{3,}\d{1,6}(?![\d*])/u',
        'vin' => '/\b(?=[A-HJ-NPR-Z0-9*]*[A-Z])(?=[A-HJ-NPR-Z0-9*]*\d)[A-HJ-NPR-Z0-9]{11}[A-HJ-NPR-Z0-9*]{6}\b/u',
    ];

    /** Detectors run on the digit-collapsed text ("3150 7901 2345 67" -> "31507901234567"). */
    private const DIGIT_DETECTORS = [
        'pinfl' => '/(?<!\d)\d{14}(?!\d)/',
        'account' => '/(?<!\d)\d{16,20}(?!\d)/',
        'phone' => '/(?<!\d)998\d{9}(?!\d)/',
    ];

    /** Uzbek case suffixes allowed after a known name ("Qodirovning", "Qodirovga"). */
    private const SUFFIX = '(?:ning|ga|ni|dan|da|dagi|niki|lar)?';

    /**
     * @param  list<string>  $publicSegments  verbatim public text (lex.uz articles) excluded from the scan
     * @return array<string, int> category => hits (empty = clean)
     */
    public function scan(string $text, ?AnonymizationContext $ctx = null, array $publicSegments = []): array
    {
        foreach ($publicSegments as $seg) {
            if ($seg !== '') {
                $text = str_replace($seg, "\n", $text);
            }
        }

        $hits = [];
        $add = function (string $cat, int $n) use (&$hits) {
            if ($n > 0) {
                $hits[$cat] = ($hits[$cat] ?? 0) + $n;
            }
        };

        $digits = self::collapseDigits($text);
        foreach (self::DETECTORS as $cat => $re) {
            $add($cat, (int) preg_match_all($re, $text));
        }
        foreach (self::DIGIT_DETECTORS as $cat => $re) {
            $add($cat, (int) preg_match_all($re, $digits));
        }

        if ($ctx) {
            $norm = self::normalize($text);
            $compact = preg_replace('/[^A-Z0-9*]/', '', mb_strtoupper(self::unifyApostrophes($text))) ?? '';
            foreach ($ctx->knownNeedles() as $cat => $needles) {
                foreach ($needles as [$needle, $mode]) {
                    $add($cat, match ($mode) {
                        'digits' => (int) preg_match_all('/(?<!\d)'.preg_quote($needle, '/').'(?!\d)/', $digits),
                        'compact' => substr_count($compact, $needle),
                        default => (int) preg_match_all('/(?<![\p{L}\d])'.self::needlePattern($needle).self::SUFFIX.'(?![\p{L}])/u', $norm),
                    });
                }
            }
        }

        return $hits;
    }

    /** lowercase + one apostrophe + single spaces */
    public static function normalize(string $s): string
    {
        return trim(preg_replace('/\s+/u', ' ', mb_strtolower(self::unifyApostrophes($s))) ?? '');
    }

    public static function unifyApostrophes(string $s): string
    {
        return str_replace(['ʻ', 'ʼ', '’', '‘', '`', 'ʹ', '´'], "'", $s);
    }

    /** Join digit groups separated by a single space / hyphen / slash / dot / colon. */
    public static function collapseDigits(string $s): string
    {
        return preg_replace('/(?<=\d)[ \-\/.:](?=\d)/', '', $s) ?? $s;
    }

    private static function needlePattern(string $needle): string
    {
        $parts = array_map(fn ($t) => preg_quote($t, '/'), explode(' ', $needle));

        return implode('\s*', $parts);
    }
}
