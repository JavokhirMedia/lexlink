<?php

namespace App\Http\Controllers\MockGov;

use App\Http\Controllers\Controller;
use App\Models\Mock\MockAdvocateLicense;
use App\Models\Mock\MockBankAccount;
use App\Models\Mock\MockBorderCrossing;
use App\Models\Mock\MockBusinessShare;
use App\Models\Mock\MockCourtCase;
use App\Models\Mock\MockCourtDecision;
use App\Models\Mock\MockGovRequest;
use App\Models\Mock\MockPerson;
use App\Models\Mock\MockProperty;
use App\Models\Mock\MockSalary;
use App\Models\Mock\MockTaxProfile;
use App\Models\Mock\MockVehicle;
use App\Support\Pinfl;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

/**
 * Inter-agency data requests. Lifecycle:
 * POST -> accepted; GET -> processing until ready_at (now + 2..8s); then completed | rejected.
 * The agency validates the legal basis itself (defence in depth — LexLink validates first).
 */
class RequestController extends Controller
{
    use Formats;

    public const TYPES = ['court_case', 'tax_info', 'salary', 'property', 'vehicle', 'business_shares', 'bank_accounts', 'border_crossing'];

    /** Agency's own rule (defence in depth): these registries answer a court decision only, never an advocate order. */
    public const COURT_ONLY = [
        'salary' => "Ish haqi ma'lumotlari soliq siri hisoblanadi va faqat sud qarori yoki ajrimi asosida taqdim etiladi. Advokat orderi yetarli emas.",
        'bank_accounts' => "Bank siri — ma'lumot faqat sudning yozma so'rovi asosida taqdim etiladi (Bank siri to'g'risidagi Qonun 10-modda). Advokat orderi yetarli emas.",
        'border_crossing' => "Chegaradan o'tish ma'lumotlari shaxsiy hayotga oid maxsus ma'lumot — faqat sud qarori asosida taqdim etiladi. Advokat orderi yetarli emas.",
    ];

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => ['required', 'in:'.implode(',', self::TYPES)],
            'subject_pinfl' => ['required', 'regex:/^\d{14}$/'],
            'legal_basis' => ['required', 'array'],
            'legal_basis.type' => ['required', 'in:court_decision,advocate_order'],
            'legal_basis.number' => ['required', 'string', 'max:64'],
            'period' => ['nullable', 'array'],
            'period.from' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'period.to' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'requester_license' => ['required', 'string', 'max:32'],
            'params' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'validation_failed',
                'message' => "So'rov formati noto'g'ri.",
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $min = (int) config('lexlink.mock_gov.delay_min', 2);
        $max = max($min, (int) config('lexlink.mock_gov.delay_max', 8));

        $row = MockGovRequest::create([
            'request_id' => 'TMP-'.bin2hex(random_bytes(8)),
            'type' => $data['type'],
            'subject_pinfl' => $data['subject_pinfl'],
            'legal_basis' => $data['legal_basis'],
            'period' => $data['period'] ?? null,
            'params' => $data['params'] ?? null,
            'requester_license' => strtoupper($data['requester_license']),
            'status' => 'accepted',
            'ready_at' => now()->addSeconds(random_int($min, $max)),
            'rejection_reason' => $this->rejectionReason($data),
        ]);

        $row->update([
            'request_id' => sprintf('GOV-%s-%06d', now()->setTimezone('Asia/Tashkent')->format('Y'), $row->id),
        ]);

        return response()->json([
            'request_id' => $row->request_id,
            'status' => 'accepted',
            'accepted_at' => $this->ts($row->created_at),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $row = MockGovRequest::where('request_id', $id)->first();

        if (! $row) {
            return $this->notFound("So'rov topilmadi.");
        }

        if (in_array($row->status, ['accepted', 'processing'], true)) {
            if ($row->ready_at->isFuture()) {
                if ($row->status === 'accepted') {
                    $row->update(['status' => 'processing']);
                }
            } elseif ($row->rejection_reason !== null) {
                $row->update(['status' => 'rejected']);
            } else {
                $row->update(['status' => 'completed', 'result' => $this->buildResult($row)]);
            }
        }

        $base = ['request_id' => $row->request_id, 'status' => $row->status, 'type' => $row->type];

        return match ($row->status) {
            'completed' => response()->json($row->result),
            'rejected' => response()->json($base + [
                'rejection_reason' => $row->rejection_reason,
                'rejected_at' => $this->ts($row->ready_at),
            ]),
            default => response()->json($base + ['expected_at' => $this->ts($row->ready_at)]),
        };
    }

    /** Agency-side legal checks. Returns Uzbek rejection reason or null. */
    private function rejectionReason(array $data): ?string
    {
        $type = $data['type'];
        $pinfl = $data['subject_pinfl'];
        $basis = $data['legal_basis'];
        $license = MockAdvocateLicense::where('license_number', strtoupper($data['requester_license']))->first();

        if (! $license) {
            return "So'rovchi advokatning litsenziyasi reestrda topilmadi.";
        }
        if ($license->status !== 'active') {
            return "Advokatlik litsenziyasi {$license->license_number} amal qilmaydi (holati: to'xtatilgan). So'rov rad etildi.";
        }

        if ($basis['type'] === 'advocate_order') {
            if (isset(self::COURT_ONLY[$type])) {
                return self::COURT_ONLY[$type];
            }
        } else {
            $decision = MockCourtDecision::where('number', trim($basis['number']))->first();

            if (! $decision) {
                return "Ko'rsatilgan sud qarori / ajrimi ({$basis['number']}) reestrda topilmadi.";
            }
            if ($decision->expires_at->endOfDay()->isPast()) {
                return "Sud ajrimi {$decision->number} muddati {$decision->expires_at->format('d.m.Y')} da tugagan.";
            }
            if ($decision->subject_pinfl !== $pinfl) {
                return "Sud ajrimi {$decision->number} so'ralgan shaxsga tegishli emas.";
            }
            if (! in_array($type, $decision->allowed_types ?? [], true)) {
                return "Sud ajrimi {$decision->number} ushbu turdagi ma'lumotni olishga ruxsat bermaydi.";
            }
            if ($decision->requester_license && $decision->requester_license !== $license->license_number) {
                return "Sud ajrimi {$decision->number} boshqa advokatga berilgan.";
            }
        }

        if ($type === 'court_case') {
            return $this->findCourtCase($pinfl, $data['params'] ?? []) ? null : "So'ralgan shaxs ishtirokidagi sud ishi topilmadi.";
        }

        if (! MockPerson::where('pinfl', $pinfl)->exists()) {
            return "JShShIR {$this->mask($pinfl)} bo'yicha shaxs topilmadi.";
        }

        return null;
    }

    private function buildResult(MockGovRequest $r): array
    {
        $person = MockPerson::where('pinfl', $r->subject_pinfl)->first();

        $data = match ($r->type) {
            'salary' => $this->salaryData($r),
            'property' => $this->propertyData($r->subject_pinfl),
            'vehicle' => $this->vehicleData($r->subject_pinfl),
            'court_case' => $this->courtCaseData($r),
            'tax_info' => $this->taxInfoData($r->subject_pinfl),
            'business_shares' => ['companies' => $this->companies($r->subject_pinfl)],
            'bank_accounts' => $this->bankAccountsData($r),
            'border_crossing' => $this->borderCrossingData($r),
        };

        return [
            'request_id' => $r->request_id,
            'status' => 'completed',
            'type' => $r->type,
            'subject' => [
                'pinfl' => $this->mask($r->subject_pinfl),
                'full_name' => $person?->shortName(),
            ],
            'data' => $data,
            'issued_at' => $this->ts($r->ready_at),
            'signature' => self::sign($data),
            'signature_alg' => 'HMAC-SHA256',
        ];
    }

    /** Real HMAC-SHA256 of the data JSON with a mock agency key. */
    public static function sign(array $data): string
    {
        $key = (string) config('lexlink.mock_gov.signing_key', 'mock-gov-agency-signing-key');

        return hash_hmac('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $key);
    }

    private function salaryData(MockGovRequest $r): array
    {
        $q = MockSalary::where('pinfl', $r->subject_pinfl)->orderBy('period')->orderBy('employer_name');

        if ($from = $r->period['from'] ?? null) {
            $q->where('period', '>=', $from);
        }
        if ($to = $r->period['to'] ?? null) {
            $q->where('period', '<=', $to);
        }

        $rows = $q->get();
        $total = (int) $rows->sum('amount');
        $monthsCount = $rows->pluck('period')->unique()->count();

        return [
            'employers' => $rows->unique('employer_tin')->map(fn (MockSalary $s) => [
                'name' => $s->employer_name,
                'tin' => $s->employer_tin,
            ])->values()->all(),
            'months' => $rows->map(fn (MockSalary $s) => [
                'period' => $s->period,
                'employer' => $s->employer_name,
                'amount' => $s->amount,
                'pension_contribution' => $s->pension_contribution,
            ])->values()->all(),
            'total' => $total,
            'average' => $monthsCount ? (int) round($total / $monthsCount) : 0,
            'period' => [
                'from' => $rows->first()?->period,
                'to' => $rows->last()?->period,
            ],
        ];
    }

    private function propertyData(string $pinfl): array
    {
        return [
            'objects' => MockProperty::where('pinfl', $pinfl)->orderBy('registered_at')->get()
                ->map(fn (MockProperty $p) => [
                    'cadastral_number' => $p->cadastral_number,
                    'type' => $p->type,
                    'address' => $p->address,
                    'area' => $p->area,
                    'share' => $p->share,
                    'registered_at' => $p->registered_at->toDateString(),
                    'encumbrances' => $p->encumbrances ?? [],
                ])->all(),
        ];
    }

    private function vehicleData(string $pinfl): array
    {
        return [
            'vehicles' => MockVehicle::where('pinfl', $pinfl)->orderBy('registered_at')->get()
                ->map(fn (MockVehicle $v) => [
                    'model' => $v->model,
                    'year' => $v->year,
                    'plate' => $v->plate,
                    'color' => $v->color,
                    'vin' => $v->vin ? substr($v->vin, 0, 11).str_repeat('*', strlen($v->vin) - 11) : null,
                    'registered_at' => $v->registered_at->toDateString(),
                    'pledge' => $v->pledge,
                ])->all(),
        ];
    }

    private function taxInfoData(string $pinfl): array
    {
        $t = MockTaxProfile::where('pinfl', $pinfl)->first();

        return [
            'tin' => $t?->tin,
            'tax_regime' => $t?->tax_regime,
            'vat_payer' => (bool) $t?->vat_payer,
            'individual_entrepreneur' => [
                'status' => $t?->is_individual_entrepreneur ? "YaTT sifatida ro'yxatdan o'tgan" : 'YaTT emas',
                'activity' => $t?->ie_activity,
            ],
            'tax_debt' => (int) ($t?->tax_debt ?? 0),
            'taxes_paid_last_year' => (int) ($t?->taxes_paid_last_year ?? 0),
            'registered_at' => $t?->registered_at?->toDateString(),
            'participations' => array_map(fn (array $c) => [
                'name' => $c['name'], 'tin' => $c['tin'], 'share_percent' => $c['share_percent'], 'status' => $c['status'],
            ], $this->companies($pinfl)),
        ];
    }

    private function companies(string $pinfl): array
    {
        return MockBusinessShare::where('pinfl', $pinfl)->orderBy('since')->get()
            ->map(fn (MockBusinessShare $s) => [
                'name' => $s->company_name,
                'tin' => $s->company_tin,
                'share_percent' => $s->share_percent,
                'charter_capital' => $s->charter_capital,
                'share_value' => (int) round($s->charter_capital * $s->share_percent / 100),
                'since' => $s->since->toDateString(),
                'status' => $s->company_status,
            ])->all();
    }

    private function bankAccountsData(MockGovRequest $r): array
    {
        $accounts = MockBankAccount::where('pinfl', $r->subject_pinfl)->orderBy('opened_at')->get();
        $to = now()->subMonth()->format('Y-m');

        return [
            'accounts' => $accounts->map(fn (MockBankAccount $a) => [
                'bank' => $a->bank_name,
                'account' => $a->account_mask,
                'currency' => $a->currency,
                'balance' => $a->balance,
                'turnover_in' => $a->turnover_in,
                'turnover_out' => $a->turnover_out,
                'opened_at' => $a->opened_at->toDateString(),
                'restrictions' => $a->restrictions ?? [],
            ])->all(),
            'totals_by_currency' => $accounts->groupBy('currency')->map(fn ($g) => [
                'balance' => (int) $g->sum('balance'),
                'turnover_in' => (int) $g->sum('turnover_in'),
                'turnover_out' => (int) $g->sum('turnover_out'),
                'accounts' => $g->count(),
            ])->all(),
            'turnover_period' => ['from' => now()->subMonths(6)->format('Y-m'), 'to' => $to, 'months' => 6],
            'as_of' => now()->setTimezone('Asia/Tashkent')->toDateString(),
        ];
    }

    private function borderCrossingData(MockGovRequest $r): array
    {
        $all = MockBorderCrossing::where('pinfl', $r->subject_pinfl)->orderBy('crossed_at')->get();
        $q = $all;
        if ($from = $r->period['from'] ?? null) {
            $q = $q->filter(fn (MockBorderCrossing $c) => $c->crossed_at->format('Y-m') >= $from);
        }
        if ($to = $r->period['to'] ?? null) {
            $q = $q->filter(fn (MockBorderCrossing $c) => $c->crossed_at->format('Y-m') <= $to);
        }
        $row = fn (MockBorderCrossing $c) => [
            'direction' => $c->direction,
            'date' => $c->crossed_at->format('Y-m-d H:i'),
            'country' => $c->country,
            'checkpoint' => $c->checkpoint,
        ];
        $last = $all->last();
        $lastExit = $all->where('direction', 'exit')->last();

        return [
            'crossings' => $q->map($row)->values()->all(),
            'currently_abroad' => $last?->direction === 'exit',
            'last_exit' => $lastExit ? $row($lastExit) : null,
            'days_abroad_last_12m' => self::daysAbroad($all->map($row)->all(), now()),
            'period' => ['from' => $r->period['from'] ?? null, 'to' => $r->period['to'] ?? null],
        ];
    }

    /** Days spent abroad within the 12 months before $now (an open exit counts up to $now). */
    public static function daysAbroad(array $crossings, CarbonInterface $now): int
    {
        $windowStart = $now->copy()->subYear();
        $days = 0;
        $out = null;
        foreach ($crossings as $c) {
            $at = Carbon::parse($c['date']);
            if ($c['direction'] === 'exit') {
                $out = $at;
            } elseif ($out) {
                $days += self::overlapDays($out, $at, $windowStart, $now);
                $out = null;
            }
        }
        if ($out) {
            $days += self::overlapDays($out, $now, $windowStart, $now);
        }

        return $days;
    }

    private static function overlapDays(CarbonInterface $a, CarbonInterface $b, CarbonInterface $from, CarbonInterface $to): int
    {
        $start = $a->max($from);
        $end = $b->min($to);

        return $end->greaterThan($start) ? (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) : 0;
    }

    private function courtCaseData(MockGovRequest $r): array
    {
        $case = $this->findCourtCase($r->subject_pinfl, $r->params ?? []);

        return $case ? RegistryController::casePayload($case) : [];
    }

    private function findCourtCase(string $pinfl, array $params): ?MockCourtCase
    {
        $number = $params['court_case_number'] ?? $params['case_number'] ?? null;

        if ($number) {
            return MockCourtCase::where('case_number', trim($number))->first();
        }

        return MockCourtCase::orderByDesc('id')->get()
            ->first(fn (MockCourtCase $c) => collect($c->parties)->contains('pinfl', $pinfl));
    }

    private function mask(string $pinfl): string
    {
        return (string) Pinfl::mask($pinfl);
    }
}
