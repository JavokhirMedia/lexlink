<?php

namespace App\Services\Ai\Anonymization;

use App\Models\User;
use App\Services\Ai\PiiMasker;
use App\Support\Pinfl;
use Illuminate\Database\Eloquent\Model;

/**
 * Everything the gateway needs to anonymise one AI call:
 *  - the PiiMasker (placeholders + local display map),
 *  - the "known PII" set (values from the case / request / response / users that must never leave the server),
 *  - what the projectors dropped or generalised (for the transparency report),
 *  - audit context (operation, subject, case, user) used when the leak guard blocks a call.
 */
final class AnonymizationContext
{
    /** @var array<string, array<string, array{0: string, 1: string}>> category => [needle => [needle, mode]] */
    private array $known = [];

    /** @var array<string, true> */
    private array $dropped = [];

    /** @var array<string, true> */
    private array $generalized = [];

    private const LEGAL_FORMS = '(?:MChJ|MCHJ|ATB|AJ|AT|XK|QK|OK|DUK|UK|YaTT|XAJ|ATIB|OAJ|YoAJ|MCHJ|LLC)';

    /** Payload keys and how their values are classified. */
    private const KEY_CATEGORIES = [
        'full_name' => 'person', 'subject_name' => 'person', 'client_name' => 'person', 'fio' => 'person', 'judge' => 'person',
        'pinfl' => 'pinfl', 'subject_pinfl' => 'pinfl', 'subject_pinfl_masked' => 'pinfl',
        'passport' => 'passport', 'phone' => 'phone', 'email' => 'email',
        'tin' => 'tin', 'employer_tin' => 'tin', 'company_tin' => 'tin', 'stir' => 'tin',
        'account' => 'account', 'account_number' => 'account', 'account_mask' => 'account', 'card' => 'account',
        'plate' => 'plate', 'vin' => 'vin', 'cadastral_number' => 'cadastral',
        'address' => 'address',
        'birth_date' => 'birth_date', 'date_of_birth' => 'birth_date', 'birthday' => 'birth_date', 'dob' => 'birth_date', 'tugilgan_sana' => 'birth_date',
        'employer' => 'organization', 'employer_name' => 'organization', 'company_name' => 'organization',
        'bank' => 'organization', 'bank_name' => 'organization', 'holder' => 'organization', 'by' => 'organization',
        'case_number' => 'case_number', 'court_case_number' => 'case_number',
        'request_id' => 'request_number', 'gov_request_id' => 'request_number',
        'requester_license' => 'license', 'license_number' => 'license', 'order_number' => 'decision_number',
    ];

    /** Parent keys whose `name` is an organisation, not a person. */
    private const ORG_PARENTS = ['employers', 'employer', 'companies', 'participations', 'organization', 'banks'];

    /** Parent keys whose `number` is a court decision / order number. */
    private const DECISION_PARENTS = ['decisions', 'decision', 'legal_basis', 'basis', 'order'];

    public function __construct(
        public readonly PiiMasker $masker = new PiiMasker,
        public readonly string $operation = 'ai',
        public readonly ?Model $subject = null,
        public readonly ?int $caseId = null,
        public readonly ?User $user = null,
    ) {}

    /** Context with no case data: only the regex detectors protect it (public law text, static queries). */
    public static function public(string $operation = 'public'): self
    {
        return new self(new PiiMasker, $operation);
    }

    // ------------------------------------------------------------------ registration

    /** Register a natural person: masker placeholder + every name token as known PII. */
    public function person(?string $name): ?string
    {
        $name = trim(preg_replace('/\s+/u', ' ', (string) $name) ?? '');
        if (mb_strlen($name) < 3 || self::isPlaceholder($name)) {
            return null;
        }
        $ph = $this->masker->addName($name);
        $this->known('person_name', $name);
        foreach (explode(' ', $name) as $token) {
            $token = trim($token, " ,.;:()\"'");
            if (mb_strlen($token) >= 4 && ! str_ends_with($token, '.')) {
                $this->known('person_name', $token);
            }
        }

        return $ph;
    }

    /** Register an organisation (employer / company / bank) under a pseudonym: [ISH_BERUVCHI_1], [KORXONA_1], [BANK_1]. */
    public function organization(string $kind, ?string $name): ?string
    {
        $name = trim((string) $name);
        if (mb_strlen($name) < 3 || self::isPlaceholder($name)) {
            return null;
        }
        $variants = [$name];
        $core = self::orgCore($name);
        if (mb_strlen($core) >= 4) {
            $variants[] = $core;
        }
        foreach ($variants as $v) {
            $this->known('organization', $v);
        }

        return $this->masker->addPhrase($kind, $name, $variants);
    }

    /** Pseudonymise an identifier (case / decision / order / request / licence number) with a restorable placeholder. */
    public function identifier(string $kind, string $category, ?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || self::isPlaceholder($value)) {
            return null;
        }
        $this->known($category, $value);

        return $this->masker->addPhrase($kind, $value, [$value]);
    }

    /** Address -> [MANZIL_n]; its distinctive fragments map to the same placeholder in free text too. */
    public function address(?string $address): ?string
    {
        $address = trim((string) $address);
        if ($address === '' || self::isPlaceholder($address)) {
            return null;
        }
        $this->known('address', $address);

        return $this->masker->addPhrase('MANZIL', $address, self::addressSegments($address));
    }

    /** Birth date of a person -> [TUGILGAN_SANA_n] (event dates are not masked). */
    public function birthDate(?string $date): ?string
    {
        $date = trim((string) $date);
        if ($date === '' || self::isPlaceholder($date)) {
            return null;
        }
        $variants = [$date];
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $date, $m)) {
            $variants[] = "{$m[3]}.{$m[2]}.{$m[1]}";
            $variants[] = "{$m[1]}-{$m[2]}-{$m[3]}";
        }
        foreach ($variants as $v) {
            $this->known('birth_date', $v);
        }

        return $this->masker->addPhrase('TUGILGAN_SANA', $date, $variants);
    }

    public function pinfl(?string $pinfl): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $pinfl) ?? '';
        if (strlen($digits) !== 14) {
            return null;
        }
        $this->known('pinfl', $digits);
        $this->known('masked_id', (string) Pinfl::mask($digits));

        return $this->masker->addPinfl($digits);
    }

    /** Register a raw value as known PII (never sent; the leak guard blocks if it shows up). */
    public function known(string $category, mixed $value): void
    {
        if (! is_string($value) && ! is_int($value)) {
            return;
        }
        $value = trim((string) $value);
        if ($value === '' || self::isPlaceholder($value)) {
            return;
        }

        if ($category === 'address') {
            foreach (self::addressSegments($value) as $seg) {
                $this->addNeedle('address', $seg);
            }

            return;
        }
        if ($category === 'phone') {
            $digits = preg_replace('/\D/', '', $value) ?? '';
            if (strlen($digits) >= 9) {
                $this->addNeedle('phone', substr($digits, -9));
            }

            return;
        }
        if ($category === 'organization') {
            $this->addNeedle('organization', $value);
            $core = self::orgCore($value);
            if (mb_strlen($core) >= 4) {
                $this->addNeedle('organization', $core);
            }

            return;
        }

        $this->addNeedle($category, $value);
    }

    /**
     * Walk a payload / record and register every identifying leaf as known PII (values only stay in memory).
     */
    public function collect(array $data, ?string $parentKey = null): self
    {
        foreach ($data as $key => $value) {
            $k = is_string($key) ? strtolower($key) : null;
            if (is_array($value)) {
                $this->collect($value, $k ?? $parentKey);

                continue;
            }
            if (! is_string($value) && ! is_int($value)) {
                continue;
            }
            $category = $k ? (self::KEY_CATEGORIES[$k] ?? null) : null;
            if ($k === 'name') {
                $category = in_array($parentKey, self::ORG_PARENTS, true) || self::looksLikeOrganization((string) $value) ? 'organization' : 'person';
            } elseif ($k === 'number' && in_array($parentKey, self::DECISION_PARENTS, true)) {
                $category = 'decision_number';
            } elseif ($k === 'court' || $k === 'court_name') {
                $category = null; // courts are public bodies; their names are generalised by the projectors
            }

            match ($category) {
                null => null,
                'person' => self::looksLikeOrganization((string) $value) ? $this->known('organization', $value) : $this->person((string) $value),
                'address' => $this->address((string) $value),
                'birth_date' => $this->birthDate((string) $value),
                'pinfl' => strlen(preg_replace('/\D/', '', (string) $value) ?? '') === 14 ? $this->pinfl((string) $value) : $this->known('masked_id', $value),
                default => $this->known($category, $value),
            };
        }

        return $this;
    }

    public function drop(string $label): void
    {
        $this->dropped[$label] = true;
    }

    public function generalize(string $label): void
    {
        $this->generalized[$label] = true;
    }

    // ------------------------------------------------------------------ accessors

    /** @return array<string, list<array{0: string, 1: string}>> category => [[needle, mode], ...] */
    public function knownNeedles(): array
    {
        return array_map('array_values', $this->known);
    }

    /** @return list<string> */
    public function droppedFields(): array
    {
        return array_keys($this->dropped);
    }

    /** @return list<string> */
    public function generalizedFields(): array
    {
        return array_keys($this->generalized);
    }

    // ------------------------------------------------------------------ helpers

    public static function isPlaceholder(string $v): bool
    {
        return (bool) preg_match('/^\[[A-Z_]+_[0-9A-Z]+\]$|^\[_+\]$/', $v);
    }

    /** '"Toshkent Tekstil" MChJ' => 'Toshkent Tekstil' */
    public static function orgCore(string $name): string
    {
        $s = preg_replace('/["«»“”„]/u', ' ', $name) ?? $name;
        $s = preg_replace('/(?<!\p{L})'.self::LEGAL_FORMS.'(?!\p{L})/u', ' ', $s) ?? $s;
        $s = preg_replace('/\b(aksiyadorlik tijorat banki|mas[\'ʼʻ’‘]uliyati cheklangan jamiyat|xususiy korxona|oilaviy korxona|advokatlik byurosi|advokatlik firmasi)\b/iu', ' ', $s) ?? $s;

        return trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
    }

    public static function looksLikeOrganization(string $v): bool
    {
        return (bool) preg_match('/["«»“”]|(?<!\p{L})'.self::LEGAL_FORMS.'(?!\p{L})|\b(bank|korxona|jamiyat|byuro|zavod|savdo|servis|qurilish|tekstil)\b/iu', $v);
    }

    /** Distinctive address fragments ("Yunusobod 4-mavze", "12-uy"); bare city / region names are not identifying. */
    public static function addressSegments(string $address): array
    {
        $out = [];
        foreach (preg_split('/[,;]/u', $address) ?: [] as $seg) {
            $seg = trim($seg);
            if (mb_strlen($seg) < 5) {
                continue;
            }
            if (preg_match('/(^|\s)(sh\.?|shahri|shahar|viloyati?|tumani|Respublikasi|Toshkent)$/iu', $seg) && ! preg_match('/\d/', $seg)) {
                continue;
            }
            $out[] = $seg;
        }

        return $out;
    }

    private function addNeedle(string $category, string $value): void
    {
        $value = trim($value);
        $digits = preg_replace('/\D/', '', $value) ?? '';
        $hasLetters = (bool) preg_match('/\p{L}/u', $value);

        if (! $hasLetters && ! str_contains($value, '*')) {
            if (strlen($digits) < 6) {
                return; // too short to be an identifier on its own (amounts, years)
            }
            $this->known[$category][$digits] = [$digits, 'digits'];

            return;
        }
        if (preg_match('/\d/', $value) || str_contains($value, '*')) {
            $compact = preg_replace('/[^A-Z0-9*]/', '', mb_strtoupper($value)) ?? '';
            if (strlen($compact) >= 6) {
                $this->known[$category][$compact] = [$compact, 'compact'];
            }

            return;
        }
        $norm = LeakGuard::normalize($value);
        if (mb_strlen($norm) >= 4) {
            $this->known[$category][$norm] = [$norm, 'text'];
        }
    }
}
