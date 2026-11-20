<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Transaction;
use App\Services\BillingService;
use App\Services\Payments\PaymentProviders;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public const TOPUP_MIN = 10_000;

    public const TOPUP_MAX = 10_000_000;

    public function __construct(private BillingService $billing) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $wallet = $this->billing->walletFor($user);
        $usage = $this->billing->usage($user);

        $transactions = Transaction::with('dataRequest:id,case_id,type')
            ->where('wallet_id', $wallet->id)
            ->latest('id')
            ->paginate(15)
            ->through(fn (Transaction $t) => [
                'id' => $t->id,
                'type' => $t->type,
                'type_label' => self::typeLabel($t->type),
                'amount' => (int) $t->amount,
                'balance_after' => (int) $t->balance_after,
                'provider' => $t->provider,
                'status' => $t->status,
                'description' => $t->description,
                'data_request_id' => $t->data_request_id,
                'created_at' => $t->created_at?->toIso8601String(),
            ]);

        return Inertia::render('billing/index', [
            'plans' => Plan::orderBy('price')->get()->map(fn (Plan $p) => self::planData($p)),
            'current_plan_code' => $usage['plan']?->code,
            'usage' => [
                'used_requests' => $usage['used_requests'],
                'included_requests' => $usage['included_requests'],
                'used_ai' => $usage['used_ai'],
                'included_ai' => $usage['included_ai'],
                'period_end' => $usage['period_end'],
            ],
            'balance' => (int) $wallet->balance,
            'quote' => $this->billing->quoteForRequest($user),
            'ai_extra_price' => (int) config('lexlink.prices.ai_extra'),
            'transactions' => $transactions,
            'topup' => ['min' => self::TOPUP_MIN, 'max' => self::TOPUP_MAX, 'presets' => [50_000, 100_000, 300_000]],
        ]);
    }

    public function changePlan(Request $request): RedirectResponse
    {
        $data = $request->validate(['plan' => ['required', 'string', Rule::exists('plans', 'code')]]);
        $plan = Plan::where('code', $data['plan'])->firstOrFail();

        $this->billing->changePlan($request->user(), $plan); // InsufficientBalance renders as errors.billing

        Inertia::flash('toast', ['type' => 'success', 'message' => "\"{$plan->name}\" tarifi faollashtirildi."]);

        return to_route('billing.index');
    }

    public function checkout(Request $request): Response
    {
        $data = $request->validate([
            'provider' => ['required', Rule::in(PaymentProviders::CODES)],
            'amount' => ['required', 'integer', 'min:'.self::TOPUP_MIN, 'max:'.self::TOPUP_MAX],
        ]);

        $provider = app(PaymentProviders::class)->for($data['provider']);

        return Inertia::render('billing/checkout', [
            'provider' => ['code' => $provider->code(), 'name' => $provider->name()],
            'amount' => (int) $data['amount'],
            'balance' => (int) $this->billing->walletFor($request->user())->balance,
            'test_card' => ['number' => '8600 0000 0000 0000', 'expiry' => '12/29'],
            'demo_code' => '666666',
        ]);
    }

    public static function planData(Plan $p): array
    {
        return [
            'id' => $p->id,
            'code' => $p->code,
            'name' => $p->name,
            'audience' => $p->audience,
            'price' => (int) $p->price,
            'included_requests' => (int) $p->included_requests,
            'extra_price' => $p->extra_price === null ? null : (int) $p->extra_price,
            'included_ai' => (int) $p->included_ai,
            'features' => $p->features ?? [],
        ];
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'topup' => "To'ldirish",
            'charge' => "So'rov",
            'refund' => 'Qaytarish',
            'subscription' => 'Obuna',
            'ai' => 'AI xizmat',
            default => $type,
        };
    }
}
