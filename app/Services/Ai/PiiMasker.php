<?php

namespace App\Services\Ai;

use App\Support\Pinfl;

/**
 * Replaces personal data with stable placeholders before anything is sent to Gemini. This is the
 * free-text half of egasizlantirish (ShMQ 16-modda); the structured half is ResponseProjector and the
 * final gate is Anonymization\AnonymizationGateway (leak guard). Gemini servers are outside Uzbekistan, so
 * only egasizlantirilgan data — no longer personal data — may be sent (that keeps ShMQ 15-modda,
 * transchegaraviy uzatish, out of scope). ShMQ 27¹ (in-country storage) covers biometric / genetic /
 * telecom-user data only and is not the reason for masking.
 *   F.I.Sh. (known names)  -> [SHAXS_1]       JShShIR (14 digits)  -> [JSHSHIR_1]
 *   passport (AA1234567)   -> [PASPORT_1]     plate (01A123BC)     -> [AVTORAQAM_1]
 *   cadastral number       -> [KADASTR_1]     phone (+998...)      -> [TELEFON_1]
 *   VIN                    -> [VIN_1]         address fields       -> [MANZIL_1]
 *   bank account (16–20 digits or masked 20206000****4417) -> [HISOBRAQAM_1]
 *   e-mail -> [EMAIL_1]    court case № (2-1001-2610/0398) -> [ISH_1]    ORD-/GOV- numbers -> [ORDER_1] / [SOROV_1]
 *   STIR after a "STIR/INN" label -> [STIR_1]    licence (AL-00123) -> [LITSENZIYA_1]
 *   birth dates ("tugʻilgan sana: 1990-05-12") -> [TUGILGAN_SANA_1]   (event dates are kept as they are)
 *   registered phrases (organisations, identifiers) -> [ISH_BERUVCHI_1], [KORXONA_1], [BANK_1], [QAROR_1] ...
 *
 * unmask() restores names locally for display; JShShIR is restored only in masked form (3*********12).
 */
class PiiMasker
{
    /** @var array<string, string> placeholder => original value */
    private array $map = [];

    /** @var array<string, string> lowercase original => placeholder */
    private array $reverse = [];

    /** @var array<string, int> */
    private array $counters = [];

    /** @var list<array{pattern: string, placeholder: string}> name variants, longest first */
    private array $names = [];

    /** @var list<array{pattern: string, placeholder: string, len: int}> registered phrases (organisations, identifiers), longest first */
    private array $phrases = [];

    private const APOS = "['ʻʼ’‘`]";

    /** Organisation names with a legal form in free text (also used by the leak guard). */
    public const ORG_RE = "/[\"«“„]\\p{Lu}[^\"»”\n]{1,80}[\"»”]\\s*(?:MChJ|MCHJ|ATB|AJ|AT|XK|QK|OK|DUK|UK|YaTT|XAJ|ATIB|OAJ|YoAJ|advokatlik\\s+byurosi|advokatlik\\s+firmasi)(?=[^\\p{L}]|$|da|ga|ni|ning|dan|dagi)|(?<![\\p{L}\\[])(?:\\p{Lu}[\\p{L}'ʻʼ’‘`]+\\s+){1,3}(?:MChJ|MCHJ|ATB|XK|QK|OK|DUK|YaTT|XAJ|ATIB)(?=[^\\p{L}]|$|da|ga|ni|ning|dan|dagi)/u";

    /** Address fragments in free text (also used by the leak guard). */
    public const ADDRESS_RE = "/(?:(?<![\\p{L}\\[])\\p{Lu}[\\p{L}'ʻʼ’‘`]+\\s+)?(?<![\\d\\[])\\d{1,4}\\s?-\\s?(?:uy|xonadon|kvartira|mavze|kvartal|daha|massiv|tor\\s+ko['ʻʼ’‘`]cha)(?!\\p{L})|(?<![\\p{L}\\[])\\p{Lu}[\\p{L}'ʻʼ’‘`]+\\s+(?:ko['ʻʼ’‘`]chasi|mahallasi|massivi|shoh\\s+ko['ʻʼ’‘`]chasi)(?:,?\\s*\\d{1,4}(?:\\s?-\\s?uy)?)?/u";

    /** Keys whose values are always masked inside arrays regardless of their shape. */
    private const KEY_KINDS = [
        'full_name' => 'SHAXS', 'name' => 'SHAXS', 'subject_name' => 'SHAXS', 'client_name' => 'SHAXS', 'fio' => 'SHAXS',
        'pinfl' => 'JSHSHIR', 'subject_pinfl' => 'JSHSHIR',
        'passport' => 'PASPORT', 'cadastral_number' => 'KADASTR', 'plate' => 'AVTORAQAM', 'vin' => 'VIN',
        'phone' => 'TELEFON', 'address' => 'MANZIL',
        'account' => 'HISOBRAQAM', 'account_number' => 'HISOBRAQAM', 'account_mask' => 'HISOBRAQAM',
    ];

    /** Keys whose `name` value is an organisation, not a person (left as is). */
    private const ORG_CONTEXT = ['employers', 'employer', 'court', 'agency', 'organization', 'companies', 'participations'];

    /** Register a person so every variant of the name (full, "Familiya I.O.", "Familiya I.", surname) maps to one placeholder. */
    public function addName(?string $fullName): ?string
    {
        $fullName = trim(preg_replace('/\s+/u', ' ', (string) $fullName) ?? '');
        if (mb_strlen($fullName) < 3 || preg_match('/^\[[A-Z_]+_\d+\]$/', $fullName)) {
            return null;
        }

        $key = mb_strtolower($fullName);
        $placeholder = $this->reverse[$key] ?? $this->placeholder('SHAXS', $fullName);

        $parts = array_values(array_filter(explode(' ', $fullName), fn ($p) => mb_strlen(rtrim($p, '.')) > 0));
        $variants = [$fullName];
        if (count($parts) >= 2) {
            [$a, $b] = $parts;
            $c = $parts[2] ?? null;
            $initial = function (string $s): string {
                if (str_ends_with($s, '.')) {
                    return $s;
                }
                // Uzbek digraphs: Sh, Ch, Ng, Oʻ, Gʻ
                $two = mb_substr($s, 0, 2);
                if (preg_match('/^(Sh|Ch|Ng|[OG]'.self::APOS.')$/u', $two)) {
                    return $two.'.';
                }

                return mb_substr($s, 0, 1).'.';
            };
            $variants[] = "{$a} {$b}";
            $variants[] = "{$b} {$a}";
            $variants[] = $a.' '.$initial($b).($c ? ' '.$initial($c) : '');
            $variants[] = $a.' '.$initial($b).($c ? $initial($c) : '');
            $variants[] = "{$a} ".$initial($b);
            $variants[] = $initial($b).' '.$a;
            if ($c) {
                $variants[] = "{$b} {$c}";
                $variants[] = "{$b} {$c} {$a}";
            }
        }

        // single tokens (surname, first name, patronymic) on their own: "Jasur", "Anvarovich"
        foreach ($parts as $i => $p) {
            if ($i <= 2 && mb_strlen($p) >= 4 && ! str_ends_with($p, '.')) {
                $variants[] = $p;
            }
        }

        foreach (array_unique($variants) as $v) {
            $this->names[] = ['pattern' => $this->namePattern($v), 'placeholder' => $placeholder, 'len' => mb_strlen($v)];
        }
        if (count($parts) >= 2 && mb_strlen($parts[0]) >= 4 && ! str_ends_with($parts[0], '.')) {
            // surname alone, swallowing up to two following capitalised words / initials ("Karimov Aziz Botirovich", "Karimov A.B.")
            $this->names[] = [
                'pattern' => '/(?<!\p{L})'.$this->quoteToken($parts[0]).'(?:\s+\p{Lu}(?:[\p{L}ʻʼ’\']+|\.\s?(?:\p{Lu}\p{Ll}?\.)?))?(?:\s+\p{Lu}[\p{L}ʻʼ’\']+)?(?=(?:ning|ga|ni|dan|da)?(?![\p{L}]))/u',
                'placeholder' => $placeholder,
                'len' => mb_strlen($parts[0]),
            ];
        }
        usort($this->names, fn ($x, $y) => $y['len'] <=> $x['len']);

        return $placeholder;
    }

    /** @param iterable<?string> $names */
    public function addNames(iterable $names): static
    {
        foreach ($names as $n) {
            $this->addName($n);
        }

        return $this;
    }

    /**
     * Register a phrase (organisation name, case / decision number ...) and its spelling variants under one
     * restorable placeholder of $kind. Matching is case-insensitive and apostrophe-variant aware.
     *
     * @param  list<string>  $variants
     */
    public function addPhrase(string $kind, string $value, array $variants = []): string
    {
        $ph = $this->placeholder($kind, $value);
        foreach (array_unique([$value, ...$variants]) as $v) {
            $v = trim($v);
            if (mb_strlen($v) < 3) {
                continue;
            }
            $tokens = preg_split('/\s+/u', $v) ?: [];
            $body = implode('\s*', array_map(fn (string $t) => $this->quoteToken($t), $tokens));
            $this->phrases[] = [
                'pattern' => '/(?<![\p{L}\d])'.$body.'(?=[^\p{L}\d]|$|ning|ga|ni|dan|da)/iu',
                'placeholder' => $ph,
                'len' => mb_strlen($v),
            ];
        }
        usort($this->phrases, fn ($x, $y) => $y['len'] <=> $x['len']);

        return $ph;
    }

    /** Restorable placeholder for a value the caller inserts itself (no free-text matching), e.g. a request id or a date. */
    public function alias(string $kind, string $value): string
    {
        return $this->placeholder($kind, $value);
    }

    /** Pre-register a known PINFL so it gets a deterministic placeholder. */
    public function addPinfl(?string $pinfl): ?string
    {
        $pinfl = preg_replace('/\D/', '', (string) $pinfl);

        return strlen((string) $pinfl) === 14 ? $this->placeholder('JSHSHIR', $pinfl) : null;
    }

    public function mask(?string $text): string
    {
        $text = (string) $text;
        if ($text === '') {
            return '';
        }

        // registered phrases first (organisation names may contain person names / digits)
        foreach ($this->phrases as $p) {
            $text = preg_replace($p['pattern'], $p['placeholder'], $text) ?? $text;
        }

        // structured identifiers first (so digits inside them are not caught by the phone rule)
        $text = preg_replace_callback('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/u', fn ($m) => $this->placeholder('EMAIL', $m[0]), $text);
        $text = preg_replace_callback('/(?<![\d\[])\d{1,2}-\d{3,4}-\d{4}\/\d{2,5}(?:-[A-Z\p{Lu}])?(?![\d])/u', fn ($m) => $this->placeholder('ISH', $m[0]), $text);
        $text = preg_replace_callback('/\bORD-\d{4}-\d+\b/u', fn ($m) => $this->placeholder('ORDER', $m[0]), $text);
        $text = preg_replace_callback('/\bGOV-\d{4}-\d+\b/u', fn ($m) => $this->placeholder('SOROV', $m[0]), $text);
        $text = preg_replace_callback('/\b[A-Z]{2}-\d{4,6}\b/u', fn ($m) => $this->placeholder('LITSENZIYA', $m[0]), $text);
        $text = preg_replace_callback('/\b(STIR|INN|TIN)([\s:№#\-]{0,4})(\d{9})(?!\d)/iu', fn ($m) => $m[1].$m[2].$this->placeholder('STIR', $m[3]), $text);
        $text = preg_replace_callback('/(?<![\d*\[])\d{1,3}\*{5,}\d{1,4}(?![\d*])/u', fn ($m) => $this->placeholder('JSHSHIR', $m[0]), $text);
        // bank accounts: 20-digit Uzbek account numbers (optionally grouped) and partially masked forms
        $text = preg_replace_callback('/(?<![\d\[])(?:\d{20}|\d{5}[\s\-]?\d{3}[\s\-]?\d[\s\-]?\d{8}[\s\-]?\d{3}|\d{4,8}\*{3,}\d{2,6})(?![\d*])/u', fn ($m) => $this->placeholder('HISOBRAQAM', $m[0]), $text);
        $text = preg_replace_callback('/(?<![\d\[])\d{14}(?!\d)/u', fn ($m) => $this->placeholder('JSHSHIR', $m[0]), $text);
        $text = preg_replace_callback('/(?<![\d\[])(?:\d{16,19}|\d{4}[\s\-]\d{4}[\s\-]\d{4}[\s\-]\d{4})(?!\d)/u', fn ($m) => $this->placeholder('HISOBRAQAM', $m[0]), $text);
        $text = preg_replace_callback('/\b\d{2}:\d{2}:\d{2}:\d{2}:\d{2}:\d{3,5}(?::\d{1,4})*\b/u', fn ($m) => $this->placeholder('KADASTR', $m[0]), $text);
        $text = preg_replace_callback('/\b[A-HJ-NPR-Z0-9]{11}[A-HJ-NPR-Z0-9*]{6}\b/u', fn ($m) => preg_match('/[A-Z]/', $m[0]) && preg_match('/\d/', $m[0]) ? $this->placeholder('VIN', $m[0]) : $m[0], $text);
        $text = preg_replace_callback('/\b[A-Z]{2}\s?\d{7}\b/u', fn ($m) => $this->placeholder('PASPORT', $m[0]), $text);
        $text = preg_replace_callback('/\b\d{2}\s?[A-Z]\s?\d{3}\s?[A-Z]{2}\b|\b\d{2}\s?\d{3}\s?[A-Z]{3}\b/u', fn ($m) => $this->placeholder('AVTORAQAM', $m[0]), $text);
        $text = preg_replace_callback('/(?:\+?998[\s\-]?)\(?\d{2}\)?[\s\-]?\d{3}[\s\-]?\d{2}[\s\-]?\d{2}\b|(?<![\d\w])\(?\d{2}\)?[\s\-]\d{3}[\s\-]\d{2}[\s\-]\d{2}\b/u', fn ($m) => $this->placeholder('TELEFON', $m[0]), $text);

        foreach ($this->names as $n) {
            $text = preg_replace($n['pattern'], $n['placeholder'], $text);
        }

        // unknown organisations in free text: "Nimadir Savdo" MChJ, «Namuna Bank» ATB, Nimadir Servis MChJ
        $text = preg_replace_callback(self::ORG_RE, fn ($m) => $this->placeholder(preg_match('/bank|ATB|kredit/iu', $m[0]) ? 'BANK' : 'KORXONA', $m[0]), $text) ?? $text;

        // free-text address fragments: "Yunusobod 4-mavze", "17-uy", "Navoiy koʻchasi 12"
        $text = preg_replace_callback(self::ADDRESS_RE, fn ($m) => $this->placeholder('MANZIL', $m[0]), $text) ?? $text;

        // birth dates of persons (event dates — salary periods, crossings, decisions — stay as they are)
        $text = preg_replace_callback("/((?:tug['ʻʼ’‘`]ilgan(?:\\s+(?:sana(?:si)?|kuni|yili))?|t\\.\\s?y\\.|birth(?:\\s?date)?|date of birth|dob)[\\s:\\-–—]{0,4})(\\d{4}-\\d{2}-\\d{2}|\\d{2}\\.\\d{2}\\.\\d{4})/iu", fn ($m) => $m[1].$this->placeholder('TUGILGAN_SANA', $m[2]), $text) ?? $text;

        // unknown full names: "Familiya Ism Otaismi(ovich|ovna)" or "... oʻgʻli / qizi"
        $w = '\p{Lu}[\p{Ll}ʻʼ’\']+';
        $text = preg_replace_callback(
            "/(?<!\p{L})(?:{$w}\s+){1,2}{$w}(?:ovich|evich|ovna|evna)(?!\p{L})|(?<!\p{L})(?:{$w}\s+){1,2}{$w}\s+(?:o['ʻʼ’]g['ʻʼ’]li|qizi)(?!\p{L})/u",
            fn ($m) => $this->placeholder('SHAXS', $m[0]),
            $text,
        );

        return $text;
    }

    /**
     * Recursively mask an array (payload / case data) for LOCAL use (fallback templates, structured facts).
     * Known PII keys are masked wholesale. What is sent to the AI is built by Anonymization\ResponseProjector instead.
     */
    public function maskArray(array $data, ?string $parentKey = null): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $k = is_string($key) ? strtolower($key) : null;
            if (is_array($value)) {
                $out[$key] = $this->maskArray($value, $k ?? $parentKey);

                continue;
            }
            if (! is_string($value) && ! is_int($value)) {
                $out[$key] = $value;

                continue;
            }

            $kind = $k ? (self::KEY_KINDS[$k] ?? null) : null;
            if ($kind === 'SHAXS' && in_array($parentKey, self::ORG_CONTEXT, true)) {
                $out[$key] = $value; // organisation name: maskArray output is local only (fallback / structured)

                continue;
            }

            if ($kind === 'SHAXS' && is_string($value)) {
                $this->addName($value);
                $out[$key] = $this->mask($value);
            } elseif ($kind === 'JSHSHIR' && is_string($value) && str_contains($value, '*')) {
                $out[$key] = $this->placeholder('JSHSHIR', $value); // already masked by the source; still hide
            } elseif ($kind !== null && $value !== '') {
                $out[$key] = $this->placeholder($kind, (string) $value);
            } else {
                $out[$key] = is_string($value) ? $this->mask($value) : $value;
            }
        }

        return $out;
    }

    /**
     * Restore placeholders for local display. JShShIR is never fully restored (masked 3*********12).
     */
    public function unmask(?string $text, bool $revealPinfl = false): string
    {
        return self::restore((string) $text, $this->displayMap($revealPinfl));
    }

    /**
     * Display map (placeholder => display value) safe to persist: no raw PINFL / passport.
     *
     * @return array<string, string>
     */
    public function displayMap(bool $revealPinfl = false): array
    {
        $out = [];
        foreach ($this->map as $ph => $value) {
            $out[$ph] = match (true) {
                str_starts_with($ph, '[JSHSHIR_') => $revealPinfl || str_contains($value, '*') ? $value : (string) Pinfl::mask($value),
                str_starts_with($ph, '[PASPORT_') => mb_substr($value, 0, 2).'*****'.mb_substr($value, -2),
                str_starts_with($ph, '[HISOBRAQAM_') => str_contains($value, '*') ? $value : mb_substr(preg_replace('/\D/', '', $value) ?? '', 0, 8).'****'.mb_substr(preg_replace('/\D/', '', $value) ?? '', -4),
                default => $value,
            };
        }

        return $out;
    }

    /** @param array<string, string> $map */
    public static function restore(string $text, array $map): string
    {
        return $map === [] ? $text : strtr($text, $map);
    }

    /** @return array<string, string> placeholder => original */
    public function map(): array
    {
        return $this->map;
    }

    private function placeholder(string $kind, string $value): string
    {
        $norm = $kind === 'SHAXS' ? mb_strtolower(trim($value)) : $kind.':'.preg_replace('/\s+/', '', mb_strtoupper($value));
        if (isset($this->reverse[$norm])) {
            return $this->reverse[$norm];
        }

        $this->counters[$kind] = ($this->counters[$kind] ?? 0) + 1;
        $ph = "[{$kind}_{$this->counters[$kind]}]";
        $this->map[$ph] = $value;
        $this->reverse[$norm] = $ph;

        return $ph;
    }

    private function quoteToken(string $t): string
    {
        $q = preg_quote($t, '/');

        return preg_replace('/'.self::APOS.'/u', self::APOS, $q) ?? $q;
    }

    private function namePattern(string $variant): string
    {
        $tokens = preg_split('/\s+/u', trim($variant)) ?: [];
        $quoted = array_map(fn (string $t) => $this->quoteToken($t), $tokens);

        // allow Uzbek case suffixes after the last token: Karimovning, Karimovga, Karimovdan...
        $end = str_ends_with($variant, '.') ? '' : '(?=[^\p{L}]|$|ning|ga|ni|dan|da|ning)';

        return '/(?<!\p{L})'.implode('\s+', $quoted).$end.'/iu';
    }
}
