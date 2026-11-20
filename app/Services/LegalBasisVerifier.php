<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\DataRequestType;
use App\Enums\LegalBasisType;
use App\Models\LegalBasis;
use App\Models\LegalCase;
use App\Services\Gov\GovApiException;
use App\Services\Gov\GovDataProvider;
use App\Support\Pinfl;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Verifies a case's legal basis against the court registry (court decisions)
 * or the advocate-order rules, and answers "which basis covers which request type".
 */
class LegalBasisVerifier
{
    /** An advocate order is self-issued, so it can never open tax secrecy (salary). */
    public const ADVOCATE_ORDER_TYPES = [
        DataRequestType::Property->value,
        DataRequestType::Vehicle->value,
        DataRequestType::CourtCase->value,
    ];

    public function __construct(
        private readonly GovDataProvider $gov,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Fills subject, allowed_types, dates and verification_status
     * (verified | not_found | expired | mismatch | pending on transport error), saves, audits LegalBasisVerified.
     */
    public function verify(LegalBasis $b): LegalBasis
    {
        $b->type === LegalBasisType::CourtDecision
            ? $this->verifyCourtDecision($b)
            : $this->verifyAdvocateOrder($b);

        $b->save();

        $this->audit->log(AuditAction::LegalBasisVerified, $b, [
            'type' => $b->type->value,
            'number' => $b->number,
            'status' => $b->verification_status,
            'allowed_types' => $b->allowed_types,
            'subject' => $b->subject_pinfl_masked,
        ], $b->case_id);

        return $b;
    }

    /**
     * Coverage of a case by its verified, unexpired bases, keyed by DataRequestType value.
     * Respects DataRequestType::acceptedBases() (salary => court decisions only).
     *
     * @return array<string, list<array{basis_id: int, basis_type: string, number: string, subject_pinfl_masked: ?string, subject_name: ?string, expires_at: ?string}>>
     */
    public function coverage(LegalCase $case): array
    {
        $bases = $case->legalBases()->where('verification_status', 'verified')->get()
            ->reject(fn (LegalBasis $b) => $b->isExpired());

        $out = [];
        foreach (DataRequestType::cases() as $type) {
            $out[$type->value] = $bases
                ->filter(fn (LegalBasis $b) => $b->allows($type) && in_array($b->type, $type->acceptedBases(), true))
                ->map(fn (LegalBasis $b) => [
                    'basis_id' => $b->id,
                    'basis_type' => $b->type->value,
                    'number' => $b->number,
                    'subject_pinfl_masked' => $b->subject_pinfl_masked,
                    'subject_name' => $b->subject_name,
                    'expires_at' => $b->expires_at?->toDateString(),
                ])->values()->all();
        }

        return $out;
    }

    private function verifyCourtDecision(LegalBasis $b): void
    {
        try {
            $d = $this->gov->courtDecision($b->number);
        } catch (GovApiException $e) {
            $b->verification_status = 'pending';
            $b->verification_payload = ['error' => 'Sud reyestri bilan aloqa yo\'q: '.$e->getMessage()];

            return;
        }

        if ($d === null) {
            $b->fill([
                'verification_status' => 'not_found',
                'verification_payload' => ['error' => 'Sud reyestrida bunday raqamli qaror topilmadi'],
                'allowed_types' => [],
                'verified_at' => now(),
            ]);

            return;
        }

        $allowed = array_values(array_intersect(
            (array) ($d['allowed_types'] ?? []),
            array_map(fn (DataRequestType $t) => $t->value, DataRequestType::cases()),
        ));

        $b->fill([
            'subject_pinfl' => $d['subject']['pinfl'] ?? null,
            'subject_name' => $d['subject']['full_name'] ?? null,
            'court_name' => $d['court'] ?? null,
            'issued_at' => $this->date($d['issued_at'] ?? null),
            'expires_at' => $this->date($d['expires_at'] ?? null),
            'allowed_types' => $allowed,
            'verification_payload' => $this->safePayload($d),
            'verified_at' => now(),
        ]);

        $b->verification_status = $b->isExpired() ? 'expired' : 'verified';
    }

    private function verifyAdvocateOrder(LegalBasis $b): void
    {
        $issuer = $b->creator;

        // Licence status re-check against registry where possible; fall back to local profile.
        $active = $issuer?->hasActiveLicense() ?? false;
        if ($active && ($number = $issuer->advocateProfile?->license_number)) {
            try {
                $licence = $this->gov->advocateLicense($number);
                if ($licence !== null && ($licence['status'] ?? null) !== 'active') {
                    $active = false;
                }
            } catch (Throwable) {
                // registry unavailable — trust the locally verified profile
            }
        }

        $allowed = array_values(array_intersect((array) ($b->allowed_types ?? []), self::ADVOCATE_ORDER_TYPES));

        $b->allowed_types = $allowed;
        $b->court_name = null;
        $b->issued_at ??= now();
        $b->verified_at = now();

        if (! $active) {
            $b->verification_status = 'mismatch';
            $b->verification_payload = ['error' => 'Orderni bergan advokatning litsenziyasi faol emas'];

            return;
        }

        if ($allowed === []) {
            $b->verification_status = 'mismatch';
            $b->verification_payload = ['error' => "Order hech bir ruxsat etilgan ma'lumot turini qamramaydi"];

            return;
        }

        $b->verification_payload = ['issuer_license' => $issuer->advocateProfile?->license_number];
        $b->verification_status = $b->isExpired() ? 'expired' : 'verified';
    }

    private function date(mixed $v): ?CarbonImmutable
    {
        return $v ? CarbonImmutable::parse($v) : null;
    }

    /** Keep registry payload but never store a raw subject PINFL in plain JSON. */
    private function safePayload(array $d): array
    {
        if (isset($d['subject']['pinfl'])) {
            $d['subject']['pinfl'] = Pinfl::mask($d['subject']['pinfl']);
        }

        return $d;
    }
}
