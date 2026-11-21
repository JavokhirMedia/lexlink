<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\DataRequestStatus;
use App\Enums\DataRequestType;
use App\Enums\LegalBasisType;
use App\Events\RequestStatusUpdated;
use App\Jobs\SubmitGovRequestJob;
use App\Models\DataRequest;
use App\Models\LegalBasis;
use App\Models\LegalCase;
use App\Models\User;
use App\Services\Gov\GovDataProvider;
use App\Support\Pinfl;
use App\Support\RequestDeadline;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

/**
 * The main flow: draft -> basis check -> advocate signature -> charge -> submit -> queue.
 * Every status change goes through transition() (state machine + broadcast + audit).
 */
class DataRequestService
{
    /** Billing module class (coded against its documented signature). */
    public const BILLING = 'App\\Services\\BillingService';

    /** Auth module licence refresher (optional). */
    public const LICENSE_CHECKER = 'App\\Services\\LicenseChecker';

    public function __construct(private AuditLogger $audit) {}

    public function create(LegalCase $case, User $by, array $data): DataRequest
    {
        $type = $data['type'] instanceof DataRequestType ? $data['type'] : DataRequestType::from($data['type']);

        $request = new DataRequest([
            'case_id' => $case->id,
            'type' => $type,
            'subject_pinfl' => $data['subject_pinfl'] ?? null,
            'subject_name' => $data['subject_name'] ?? null,
            'params' => $this->normalizeParams($type, $data['params'] ?? []),
            'purpose' => $data['purpose'] ?? null,
            'request_text' => $data['request_text'] ?? null,
            'status' => DataRequestStatus::Draft,
            'created_by' => $by->id,
        ]);
        $request->setRelation('legalCase', $case);

        $check = $this->checkBasis($request);
        $request->legal_basis_id = $check['basis']?->id;
        $request->save();

        $this->audit->log(AuditAction::RequestCreated, $request, [
            'type' => $type->value,
            'subject' => $request->subject_pinfl_masked,
        ], $case->id, $by);

        return $request;
    }

    public function update(DataRequest $request, array $data): DataRequest
    {
        if (! in_array($request->status, [DataRequestStatus::Draft, DataRequestStatus::AwaitingSignature], true)) {
            throw ValidationException::withMessages(['status' => "Yuborilgan so'rovni tahrirlab bo'lmaydi"]);
        }

        $type = isset($data['type']) ? DataRequestType::from($data['type']) : $request->type;
        $request->fill([
            'type' => $type,
            'subject_pinfl' => $data['subject_pinfl'] ?? $request->subject_pinfl,
            'subject_name' => $data['subject_name'] ?? $request->subject_name,
            'params' => $this->normalizeParams($type, $data['params'] ?? $request->params ?? []),
            'purpose' => $data['purpose'] ?? $request->purpose,
            'request_text' => $data['request_text'] ?? $request->request_text,
        ]);
        $request->legal_basis_id = $this->checkBasis($request)['basis']?->id;
        $request->save();

        return $request;
    }

    /**
     * Legal rule #1: the case must hold a verified, unexpired basis for the same person
     * (PINFL) that covers this data type; court-only types (LegalMatrix access 'court') need a court decision.
     *
     * Works on unsaved instances too (live check on the create page).
     *
     * @return array{ok: bool, reason: ?string, basis: ?LegalBasis}
     */
    public function checkBasis(DataRequest $r): array
    {
        $type = $r->type;
        $case = $r->legalCase ?? LegalCase::find($r->case_id);
        $bases = $case ? $case->legalBases()->orderByDesc('id')->get() : collect();

        $blocked = fn (string $reason) => ['ok' => false, 'reason' => $reason, 'basis' => null];

        if (! $type) {
            return $blocked("So'rov turi tanlanmagan");
        }
        if (! $r->subject_pinfl || ! Pinfl::isValid($r->subject_pinfl)) {
            return $blocked("So'rovdagi shaxsning JShShIR raqami ko'rsatilmagan yoki noto'g'ri (14 raqam)");
        }
        if ($bases->isEmpty()) {
            return $blocked("Ishda huquqiy asos yo'q — avval sud ajrimi yoki advokat orderini qo'shing");
        }

        // 1. basis type accepted for this data type (LegalMatrix::acceptedBases)
        $accepted = $bases->filter(fn (LegalBasis $b) => in_array($b->type, $type->acceptedBases(), true));
        if ($accepted->isEmpty()) {
            // court-only types (LegalMatrix access = 'court'): salary, bank_accounts, border_crossing
            return $blocked($type->access() === 'court'
                ? "{$type->secrecyRegime()} so'raladi (advokat orderi yetarli emas)"
                : "Ishdagi asos turi ushbu so'rov uchun qabul qilinmaydi");
        }

        // 2. verified by the registry
        $verified = $accepted->filter(fn (LegalBasis $b) => $b->isVerified());
        if ($verified->isEmpty()) {
            $b = $accepted->first();

            return $blocked(match ($b->verification_status) {
                'expired' => 'Asos muddati o\'tgan',
                'not_found' => "{$this->basisWord($b)} {$b->number} reyestrda topilmadi",
                'mismatch' => "{$this->basisWord($b)} {$b->number} ma'lumotlari reyestrga mos kelmadi",
                default => "{$this->basisWord($b)} {$b->number} hali tekshirilmagan — tasdiqlanishini kuting",
            });
        }

        // 3. same person
        $hash = Pinfl::hash($r->subject_pinfl);
        $samePerson = $verified->filter(fn (LegalBasis $b) => $b->subject_pinfl_hash !== null && hash_equals($b->subject_pinfl_hash, $hash));
        if ($samePerson->isEmpty()) {
            return $blocked("Asosdagi shaxs JShShIR so'rovdagi shaxsga mos emas");
        }

        // 4. not expired
        $valid = $samePerson->filter(fn (LegalBasis $b) => ! $b->isExpired());
        if ($valid->isEmpty()) {
            $b = $samePerson->first();

            return $blocked("Asos muddati o'tgan ({$this->basisWord($b)} {$b->number} {$b->expires_at?->format('d.m.Y')} gacha amal qilgan)");
        }

        // 5. covers the data type
        $covering = $valid->filter(fn (LegalBasis $b) => $this->covers($b, $type));
        if ($covering->isEmpty()) {
            $b = $valid->first();

            return $blocked("{$this->basisWord($b)} {$b->number} {$this->typeWord($type)} ma'lumotini qamramaydi");
        }

        $basis = ($r->legal_basis_id ? $covering->firstWhere('id', $r->legal_basis_id) : null) ?? $covering->first();

        return ['ok' => true, 'reason' => null, 'basis' => $basis];
    }

    /** Lawyer (or advocate) hands a draft to the advocate for signing. */
    public function requestSignature(DataRequest $r, User $by): DataRequest
    {
        if ($r->status !== DataRequestStatus::Draft) {
            throw ValidationException::withMessages(['status' => "Faqat loyiha holatidagi so'rovni imzoga yuborish mumkin"]);
        }
        $this->assertComplete($r);

        return $this->transition($r, DataRequestStatus::AwaitingSignature, [], $by);
    }

    public function signAndSubmit(DataRequest $r, User $advocate): DataRequest
    {
        $case = $r->legalCase;

        // Rule #2: only an advocate member of the case with an ACTIVE licence (checked now).
        if (! $advocate->isAdvocate() || ! $this->isAdvocateMember($case, $advocate)) {
            throw new AuthorizationException("So'rovni faqat ishga biriktirilgan advokat imzolaydi va yuboradi");
        }
        $this->refreshLicense($advocate);
        if (! $advocate->hasActiveLicense()) {
            $this->audit->log(AuditAction::RequestBlocked, $r, ['reason' => 'license_inactive'], $case->id, $advocate);
            throw new AuthorizationException("Advokatlik litsenziyangiz faol emas — imzolash huquqi to'xtatilgan");
        }

        if (! in_array($r->status, [DataRequestStatus::Draft, DataRequestStatus::AwaitingSignature], true)) {
            throw ValidationException::withMessages(['status' => "So'rov allaqachon yuborilgan"]);
        }
        $this->assertComplete($r);

        // Rule #1: basis must cover person + data type + period.
        $check = $this->checkBasis($r);
        if (! $check['ok']) {
            $this->audit->log(AuditAction::RequestBlocked, $r, [
                'reason' => $check['reason'],
                'type' => $r->type->value,
                'subject' => $r->subject_pinfl_masked,
            ], $case->id, $advocate);
            throw ValidationException::withMessages(['basis' => $check['reason']]);
        }

        $key = 'data-request-submit:'.$advocate->id;
        if (RateLimiter::tooManyAttempts($key, (int) config('lexlink.rate_limit_per_minute', 10))) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages(['rate' => "Juda ko'p so'rov. {$seconds} soniyadan so'ng qayta urinib ko'ring"]);
        }
        RateLimiter::hit($key, 60);

        // Answer deadline (Advokatura to'g'risidagi Qonun 7¹-modda): 15 kun / 3 ish kuni if proceedings started.
        $deadline = RequestDeadline::compute($r, $check['basis'], now(), app(GovDataProvider::class));

        $r = DB::transaction(function () use ($r, $advocate, $check, $case, $deadline) {
            if ($billing = $this->billing()) {
                try {
                    $billing->chargeForRequest($r, $advocate); // sets cost + charged_from_subscription
                } catch (Throwable $e) {
                    if (is_a($e, 'App\\Exceptions\\InsufficientBalance')) {
                        throw ValidationException::withMessages([
                            'billing' => $e->getMessage() ?: 'Balans yetarli emas',
                        ]);
                    }
                    throw $e;
                }
                $r->refresh();
            }

            $signedAt = now();
            if ($r->status === DataRequestStatus::Draft) {
                $r = $this->transition($r, DataRequestStatus::AwaitingSignature, [], $advocate, audit: false);
            }

            $r->forceFill([
                'legal_basis_id' => $check['basis']->id,
                'signed_by' => $advocate->id,
                'signed_at' => $signedAt,
                'signature' => hash('sha256', implode('|', [$r->id, (string) $r->request_text, $advocate->id, $signedAt->toIso8601String()])),
            ])->save();

            $this->audit->log(AuditAction::RequestSigned, $r, [
                'signature' => substr($r->signature, 0, 16),
                'license' => $advocate->advocateProfile?->license_number,
                'basis' => $check['basis']->number,
            ], $case->id, $advocate);

            return $this->transition($r, DataRequestStatus::Submitted, ['submitted_at' => now()] + $deadline, $advocate);
        });

        SubmitGovRequestJob::dispatch($r->id);

        return $r->fresh();
    }

    public function transition(DataRequest $r, DataRequestStatus $to, array $attrs = [], ?User $by = null, bool $audit = true): DataRequest
    {
        $from = $r->status;
        if (! $from->canTransitionTo($to)) {
            throw new LogicException("Invalid data request transition {$from->value} -> {$to->value}");
        }

        $r->forceFill(array_merge($this->timestampsFor($to), $attrs, ['status' => $to]))->save();

        if ($audit) {
            [$action, $event] = match ($to) {
                DataRequestStatus::Draft => [AuditAction::RequestCreated, 'returned_to_draft'],
                DataRequestStatus::AwaitingSignature => [AuditAction::RequestCreated, 'sent_for_signature'],
                DataRequestStatus::Submitted => [AuditAction::RequestSubmitted, 'submitted'],
                DataRequestStatus::Processing => [AuditAction::RequestSubmitted, 'accepted_by_agency'],
                DataRequestStatus::Completed => [AuditAction::ResponseReceived, 'completed'],
                DataRequestStatus::Rejected => [AuditAction::ResponseReceived, 'rejected'],
                DataRequestStatus::Failed => [AuditAction::ResponseReceived, 'technical_failure'],
            };
            $meta = ['event' => $event, 'from' => $from->value, 'to' => $to->value, 'gov_request_id' => $r->gov_request_id];
            if ($to === DataRequestStatus::Rejected) {
                $meta['reason'] = $r->rejection_reason;
            }
            if ($to === DataRequestStatus::Failed) {
                $meta['reason'] = $r->failure_reason;
            }
            $this->audit->log($action, $r, array_filter($meta, fn ($v) => $v !== null), $r->case_id, $by);
        }

        try {
            event(new RequestStatusUpdated($r));
        } catch (Throwable $e) {
            Log::warning('RequestStatusUpdated broadcast failed: '.$e->getMessage());
        }

        return $r;
    }

    /** Refund on rejection / technical failure (rule #7). */
    public function refund(DataRequest $r): void
    {
        $billing = $this->billing();
        if (! $billing) {
            return;
        }
        try {
            $billing->refundForRequest($r);
        } catch (Throwable $e) {
            Log::error("Refund failed for data request {$r->id}: ".$e->getMessage());
        }
    }

    public function quote(User $user): array
    {
        $billing = $this->billing();
        if (! $billing) {
            return ['cost' => 0, 'from_subscription' => true];
        }
        try {
            $quote = $billing->quoteForRequest($user);

            return ['cost' => (int) ($quote['cost'] ?? 0), 'from_subscription' => (bool) ($quote['from_subscription'] ?? false)];
        } catch (Throwable) {
            return ['cost' => 0, 'from_subscription' => false];
        }
    }

    public function isAdvocateMember(?LegalCase $case, User $user): bool
    {
        if (! $case) {
            return false;
        }
        if ($case->owner_id === $user->id && $user->isAdvocate()) {
            return true;
        }

        return $case->memberships()->where('user_id', $user->id)->where('role', 'advocate')->exists();
    }

    private function refreshLicense(User $user): void
    {
        if (class_exists(self::LICENSE_CHECKER) || app()->bound(self::LICENSE_CHECKER)) {
            try {
                app(self::LICENSE_CHECKER)->refresh($user);
            } catch (Throwable $e) {
                Log::warning('Licence refresh failed, using stored status: '.$e->getMessage());
            }
        }
        $user->unsetRelation('advocateProfile');
        $user->load('advocateProfile');
    }

    private function billing(): ?object
    {
        if (app()->bound(self::BILLING) || class_exists(self::BILLING)) {
            return app(self::BILLING);
        }

        return null; // TODO(billing): BillingService not available yet — requests are free until then.
    }

    private function assertComplete(DataRequest $r): void
    {
        $errors = [];
        if (! $r->subject_pinfl) {
            $errors['subject_pinfl'] = "Shaxs JShShIR ko'rsatilmagan";
        }
        if (! trim((string) $r->request_text)) {
            $errors['request_text'] = "So'rov matni bo'sh";
        }
        if ($r->type === DataRequestType::Salary && (empty($r->params['period_from']) || empty($r->params['period_to']))) {
            $errors['params.period_from'] = "Ish haqi so'rovi uchun davr ko'rsatilishi shart";
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function normalizeParams(DataRequestType $type, array $params): array
    {
        $out = [];
        if ($type === DataRequestType::Salary) {
            $out['period_from'] = $params['period_from'] ?? now()->subMonths(6)->format('Y-m');
            $out['period_to'] = $params['period_to'] ?? now()->subMonth()->format('Y-m');
        }
        // optional period (months) for bank turnover / border crossings / tax data
        if (in_array($type, [DataRequestType::BankAccounts, DataRequestType::BorderCrossing, DataRequestType::TaxInfo], true)) {
            foreach (['period_from', 'period_to'] as $k) {
                if (! empty($params[$k])) {
                    $out[$k] = $params[$k];
                }
            }
        }
        if ($type === DataRequestType::Property && ! empty($params['cadastral_number'])) {
            $out['cadastral_number'] = $params['cadastral_number'];
        }
        if ($type === DataRequestType::CourtCase && ! empty($params['court_case_number'])) {
            $out['court_case_number'] = $params['court_case_number'];
        }

        return $out;
    }

    private function timestampsFor(DataRequestStatus $to): array
    {
        return match ($to) {
            DataRequestStatus::Completed, DataRequestStatus::Rejected, DataRequestStatus::Failed => ['completed_at' => now()],
            default => [],
        };
    }

    private function covers(LegalBasis $b, DataRequestType $type): bool
    {
        if ($b->allows($type)) {
            return true;
        }

        // Advocate orders without an explicit list cover everything their type is accepted for.
        return empty($b->allowed_types) && $b->type === LegalBasisType::AdvocateOrder
            && in_array(LegalBasisType::AdvocateOrder, $type->acceptedBases(), true);
    }

    public function basisWord(LegalBasis $b): string
    {
        return $b->type === LegalBasisType::CourtDecision ? 'Sud ajrimi' : 'Advokat orderi';
    }

    public function typeWord(DataRequestType $type): string
    {
        return match ($type) {
            DataRequestType::CourtCase => 'sud ishi',
            DataRequestType::Salary => 'ish haqi',
            DataRequestType::Property => "ko'chmas mulk",
            DataRequestType::Vehicle => 'avtotransport',
            DataRequestType::TaxInfo => 'soliq',
            DataRequestType::BusinessShares => 'yuridik shaxslardagi ulush',
            DataRequestType::BankAccounts => 'bank hisobvaraqlari',
            DataRequestType::BorderCrossing => "chegaradan o'tish",
        };
    }
}
