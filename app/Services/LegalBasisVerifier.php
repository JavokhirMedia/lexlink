<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\DataRequestType;
use App\Enums\LegalBasisType;
use App\Models\LegalBasis;
use App\Models\LegalCase;
use App\Services\Gov\GovApiException;
use App\Services\Gov\GovDataProvider;
use App\Support\LegalMatrix;
use App\Support\Pinfl;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Verifies a case's legal basis against the court registry (court decisions)
 * or the advocate-order rules, and answers "which basis covers which request type".
 */
class LegalBasisVerifier
{
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

    /**
     * Advocate order (advokat so'rovi + order, Advokatura to'g'risidagi Qonun 6, 7¹-moddalar):
     * the order number is checked against the e-order registry — it must exist, be active and be
     * issued by THIS advocate's licence. The order authorises representing the client; the person whose
     * data is requested (subject) is entered by the advocate and tied to the order matter via `purpose`.
     */
    private function verifyAdvocateOrder(LegalBasis $b): void
    {
        $issuer = $b->creator;
        $licence = $issuer?->advocateProfile?->license_number;

        $allowed = array_values(array_intersect((array) ($b->allowed_types ?? []), self::advocateOrderTypes()));
        $b->allowed_types = $allowed;
        $b->court_name = null;
        $b->verified_at = now();

        $fail = function (string $status, string $error, array $extra = []) use ($b): void {
            $b->verification_status = $status;
            $b->verification_payload = ['error' => $error, 'purpose' => $b->purpose] + $extra;
        };

        // 1. issuing advocate's licence must be active (registry re-check where possible)
        $active = $issuer?->hasActiveLicense() ?? false;
        if ($active && $licence) {
            try {
                $record = $this->gov->advocateLicense($licence);
                if ($record !== null && ($record['status'] ?? null) !== 'active') {
                    $active = false;
                }
            } catch (Throwable) {
                // registry unavailable — trust the locally verified profile
            }
        }
        if (! $active) {
            $fail('mismatch', 'Orderni bergan advokatning litsenziyasi faol emas');

            return;
        }

        // 2. e-order registry
        try {
            $order = $this->gov->advocateOrder($b->number);
        } catch (GovApiException $e) {
            $b->verification_status = 'pending';
            $b->verification_payload = ['error' => "Elektron orderlar reyestri bilan aloqa yo'q: ".$e->getMessage(), 'purpose' => $b->purpose];

            return;
        }

        if ($order === null) {
            $fail('not_found', "Advokatlar palatasining elektron orderlar reyestrida {$b->number} raqamli order topilmadi");

            return;
        }

        $orderData = $this->safeOrder($order);

        if (($order['status'] ?? null) !== 'active') {
            $fail('mismatch', "Order {$b->number} bekor qilingan — undan foydalanib bo'lmaydi", ['order' => $orderData]);

            return;
        }

        $orderLicence = strtoupper((string) ($order['license_number'] ?? ''));
        if ($licence === null || ! hash_equals($orderLicence, strtoupper($licence))) {
            $fail('mismatch', "Order {$b->number} boshqa advokatga ({$orderLicence}) berilgan — faqat o'zingiz bergan orderdan foydalanishingiz mumkin", ['order' => $orderData]);

            return;
        }

        if ($allowed === []) {
            $fail('mismatch', "Order hech bir ruxsat etilgan ma'lumot turini qamramaydi", ['order' => $orderData]);

            return;
        }

        $b->issued_at = $this->date($order['issued_at'] ?? null) ?? ($b->issued_at ?? now());
        $b->verification_payload = [
            'issuer_license' => $licence,
            'purpose' => $b->purpose,
            'order' => $orderData,
            'client_name' => $order['client']['full_name'] ?? null,
            'client_pinfl_masked' => $orderData['client']['pinfl_masked'] ?? null,
            'client_pinfl_hash' => isset($order['client']['pinfl']) ? Pinfl::hash((string) $order['client']['pinfl']) : null,
        ];
        $b->verification_status = $b->isExpired() ? 'expired' : 'verified';
    }

    /**
     * Types an advocate order may cover — LegalMatrix access = 'order'. Never salary / bank / border:
     * those secrecy regimes are opened only by a court decision.
     */
    public static function advocateOrderTypes(): array
    {
        return LegalMatrix::orderTypes();
    }

    /** Registry order without the raw client PINFL. */
    private function safeOrder(array $o): array
    {
        $pinfl = $o['client']['pinfl'] ?? null;
        unset($o['client']['pinfl'], $o['checked_at']);
        $o['client']['pinfl_masked'] ??= $pinfl ? Pinfl::mask((string) $pinfl) : null;

        return $o;
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
