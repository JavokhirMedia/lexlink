<?php

namespace App\Services\Ai;

use App\Support\Pinfl;

/**
 * Replaces personal data with stable placeholders before anything is sent to Gemini
 * (ShMQ 27¹-modda — personal data of citizens is processed on servers in Uzbekistan):
 *   F.I.Sh. (known names)  -> [SHAXS_1]       JShShIR (14 digits)  -> [JSHSHIR_1]
 *   passport (AA1234567)   -> [PASPORT_1]     plate (01A123BC)     -> [AVTORAQAM_1]
 *   cadastral number       -> [KADASTR_1]     phone (+998...)      -> [TELEFON_1]
 *   VIN                    -> [VIN_1]         address fields       -> [MANZIL_1]
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

    private const APOS = "['ʻʼ’‘`]";

    /** Keys whose values are always masked inside arrays regardless of their shape. */
    private const KEY_KINDS = [
        'full_name' => 'SHAXS', 'name' => 'SHAXS', 'subject_name' => 'SHAXS', 'client_name' => 'SHAXS', 'fio' => 'SHAXS',
        'pinfl' => 'JSHSHIR', 'subject_pinfl' => 'JSHSHIR',
        'passport' => 'PASPORT', 'cadastral_number' => 'KADASTR', 'plate' => 'AVTORAQAM', 'vin' => 'VIN',
        'phone' => 'TELEFON', 'address' => 'MANZIL',
    ];

    /** Keys whose `name` value is an organisation, not a person (left as is). */
    private const ORG_CONTEXT = ['employers', 'employer', 'court', 'agency', 'organization'];

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

        // structured identifiers first (so digits inside them are not caught by the phone rule)
        $text = preg_replace_callback('/(?<![\d\[])\d{14}(?!\d)/u', fn ($m) => $this->placeholder('JSHSHIR', $m[0]), $text);
        $text = preg_replace_callback('/\b\d{2}:\d{2}:\d{2}:\d{2}:\d{2}:\d{3,5}(?::\d{1,4})*\b/u', fn ($m) => $this->placeholder('KADASTR', $m[0]), $text);
        $text = preg_replace_callback('/\b[A-HJ-NPR-Z0-9]{11}[A-HJ-NPR-Z0-9*]{6}\b/u', fn ($m) => preg_match('/[A-Z]/', $m[0]) && preg_match('/\d/', $m[0]) ? $this->placeholder('VIN', $m[0]) : $m[0], $text);
        $text = preg_replace_callback('/\b[A-Z]{2}\s?\d{7}\b/u', fn ($m) => $this->placeholder('PASPORT', $m[0]), $text);
        $text = preg_replace_callback('/\b\d{2}\s?[A-Z]\s?\d{3}\s?[A-Z]{2}\b|\b\d{2}\s?\d{3}\s?[A-Z]{3}\b/u', fn ($m) => $this->placeholder('AVTORAQAM', $m[0]), $text);
        $text = preg_replace_callback('/(?:\+?998[\s\-]?)\(?\d{2}\)?[\s\-]?\d{3}[\s\-]?\d{2}[\s\-]?\d{2}\b|(?<![\d\w])\(?\d{2}\)?[\s\-]\d{3}[\s\-]\d{2}[\s\-]\d{2}\b/u', fn ($m) => $this->placeholder('TELEFON', $m[0]), $text);

        foreach ($this->names as $n) {
            $text = preg_replace($n['pattern'], $n['placeholder'], $text);
        }

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
     * Recursively mask an array (payload / case data). Known PII keys are masked wholesale.
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
                $kind = null;
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
