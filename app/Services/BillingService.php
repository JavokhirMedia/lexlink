<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Exceptions\InsufficientBalance;
use App\Models\DataRequest;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Money rules (spec "Monetizatsiya"):
 *  - A subscription includes N requests per month (subscriptions.used_requests vs plans.included_requests).
 *  - Beyond the quota each request costs plans.extra_price from the wallet; extra_price = null => not allowed.
 *  - The response itself is NEVER paywalled — billing gates only submission and AI extras.
 *  - Charge on submit, refund on rejection / technical failure (idempotent).
 *
 * Every balance mutation runs in a DB transaction with a row lock on the wallet and writes
 * a `transactions` row (with balance_after) plus an audit entry.
 */
class BillingService
{
    public function __construct(private AuditLogger $audit) {}

    public function walletFor(User $u): Wallet
    {
        return Wallet::firstOrCreate(['user_id' => $u->id], ['balance' => 0]);
    }

    /**
     * Current-month subscription. Rolls over to a new period on the same plan when the
     * previous one ended (demo: renewal is not charged), or puts the user on the Start plan.
     */
    public function currentSubscription(User $u): ?Subscription
    {
        $today = now()->toDateString();

        $sub = Subscription::with('plan')
            ->where('user_id', $u->id)
            ->whereDate('period_start', '<=', $today)
            ->whereDate('period_end', '>=', $today)
            ->latest('id')
            ->first();

        if ($sub) {
            return $sub;
        }

        $last = Subscription::with('plan')->where('user_id', $u->id)->latest('id')->first();
        $plan = $last?->plan ?? Plan::where('code', 'start')->first();

        if (! $plan) {
            return null;
        }

        return $this->openPeriod($u, $plan);
    }

    /** @return array{cost:int, from_subscription:bool, allowed:bool, reason:?string} */
    public function quoteForRequest(User $u): array
    {
        $sub = $this->currentSubscription($u);

        if ($sub && $sub->used_requests < $sub->plan->included_requests) {
            return ['cost' => 0, 'from_subscription' => true, 'allowed' => true, 'reason' => null];
        }

        $price = $sub?->plan->extra_price;

        if ($price === null) {
            return [
                'cost' => 0,
                'from_subscription' => false,
                'allowed' => false,
                'reason' => InsufficientBalance::planDoesNotAllow()->getMessage(),
            ];
        }

        $balance = (int) $this->walletFor($u)->balance;

        return [
            'cost' => (int) $price,
            'from_subscription' => false,
            'allowed' => $balance >= $price,
            'reason' => $balance >= $price ? null : InsufficientBalance::forAmount((int) $price, $balance)->getMessage(),
        ];
    }

    /**
     * Charge for submitting a data request: from the subscription quota first, then plans.extra_price
     * from the wallet. Idempotent — a request that is already charged (and not refunded) is skipped.
     *
     * @throws InsufficientBalance
     */
    public function chargeForRequest(DataRequest $r, User $u): void
    {
        $result = DB::transaction(function () use ($r, $u) {
            $locked = DataRequest::whereKey($r->getKey())->lockForUpdate()->firstOrFail();

            if ($this->isCharged($locked)) {
                return null;
            }

            $wallet = $this->lockWallet($u);
            $sub = $this->lockSubscription($u);

            if ($sub && $sub->used_requests < $sub->plan->included_requests) {
                $sub->increment('used_requests');
                $tx = $this->record($wallet, 'charge', 0, [
                    'data_request_id' => $locked->id,
                    'description' => "So'rov: obuna doirasida ({$sub->used_requests} / {$sub->plan->included_requests})",
                ]);
                $attrs = ['cost' => 0, 'charged_from_subscription' => true];
            } else {
                $price = $sub?->plan->extra_price;

                if ($price === null) {
                    throw InsufficientBalance::planDoesNotAllow();
                }

                if ($wallet->balance < $price) {
                    throw InsufficientBalance::forAmount((int) $price, (int) $wallet->balance);
                }

                $tx = $this->record($wallet, 'charge', -$price, [
                    'data_request_id' => $locked->id,
                    'description' => "So'rov: tarifdan tashqari (".$locked->type->label().')',
                ]);
                $attrs = ['cost' => (int) $price, 'charged_from_subscription' => false];
            }

            $locked->forceFill($attrs)->save();

            $this->audit->log(AuditAction::Charge, $locked, [
                'amount' => $attrs['cost'],
                'from_subscription' => $attrs['charged_from_subscription'],
                'transaction_id' => $tx->id,
                'balance_after' => $tx->balance_after,
            ], user: $u);

            return $attrs;
        });

        if ($result !== null) {
            $r->forceFill($result)->syncOriginalAttributes(array_keys($result));
        }
    }

    /**
     * Refund a request charge (rejection / technical failure). Wallet charges go back to the wallet,
     * subscription charges give the quota back. Idempotent — calling it twice does nothing.
     */
    public function refundForRequest(DataRequest $r): void
    {
        DB::transaction(function () use ($r) {
            $locked = DataRequest::whereKey($r->getKey())->lockForUpdate()->firstOrFail();

            if (! $this->isCharged($locked)) {
                return;
            }

            $charge = Transaction::where('data_request_id', $locked->id)->where('type', 'charge')->latest('id')->firstOrFail();
            $wallet = Wallet::whereKey($charge->wallet_id)->lockForUpdate()->firstOrFail();
            $owner = $wallet->user;
            $amount = -(int) $charge->amount; // charge rows are negative (or 0 for quota)

            if ($amount === 0) {
                $sub = $this->lockSubscription($owner);
                if ($sub && $sub->used_requests > 0 && $charge->created_at->betweenIncluded($sub->period_start->startOfDay(), $sub->period_end->endOfDay())) {
                    $sub->decrement('used_requests');
                }
                $description = "Qaytarildi: obuna limiti tiklandi (so'rov #{$locked->id})";
            } else {
                $description = "Qaytarildi: so'rov #{$locked->id} bajarilmadi";
            }

            $tx = $this->record($wallet, 'refund', $amount, [
                'data_request_id' => $locked->id,
                'description' => $description,
            ]);

            $this->audit->log(AuditAction::Refund, $locked, [
                'amount' => $amount,
                'from_subscription' => $amount === 0,
                'status' => $locked->status?->value,
                'transaction_id' => $tx->id,
                'balance_after' => $tx->balance_after,
            ], user: $owner);
        });
    }

    /**
     * Charge an AI add-on (claim draft, compare...). Plan quota (included_ai) first, then
     * config('lexlink.prices.ai_extra') from the wallet. Returns the amount charged (0 within plan).
     *
     * @throws InsufficientBalance
     */
    public function chargeAi(User $u, string $kind): int
    {
        return DB::transaction(function () use ($u, $kind) {
            $wallet = $this->lockWallet($u);
            $sub = $this->lockSubscription($u);

            if ($sub && $sub->used_ai < $sub->plan->included_ai) {
                $sub->increment('used_ai');
                $tx = $this->record($wallet, 'ai', 0, [
                    'description' => "AI ({$kind}): obuna doirasida ({$sub->used_ai} / {$sub->plan->included_ai})",
                ]);
                $cost = 0;
            } else {
                $cost = (int) config('lexlink.prices.ai_extra');

                if ($wallet->balance < $cost) {
                    throw InsufficientBalance::forAmount($cost, (int) $wallet->balance);
                }

                $tx = $this->record($wallet, 'ai', -$cost, ['description' => "AI ({$kind}): qo'shimcha xizmat"]);
            }

            $this->audit->log(AuditAction::Charge, $tx, [
                'kind' => $kind,
                'amount' => $cost,
                'from_subscription' => $cost === 0,
                'balance_after' => $tx->balance_after,
            ], user: $u);

            return $cost;
        });
    }

    /** Credit the wallet after a successful payment. Idempotent per (provider, external id). */
    public function topup(User $u, int $amount, string $provider, ?string $externalId = null): Transaction
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Topup amount must be positive.');
        }

        return DB::transaction(function () use ($u, $amount, $provider, $externalId) {
            $wallet = $this->lockWallet($u);

            if ($externalId !== null) {
                $existing = Transaction::where('wallet_id', $wallet->id)->where('type', 'topup')
                    ->where('provider', $provider)->where('external_id', $externalId)->first();
                if ($existing) {
                    return $existing;
                }
            }

            $tx = $this->record($wallet, 'topup', $amount, [
                'provider' => $provider,
                'external_id' => $externalId,
                'description' => "Balans to'ldirildi (".ucfirst($provider).')',
            ]);

            $this->audit->log(AuditAction::WalletTopup, $tx, [
                'amount' => $amount,
                'provider' => $provider,
                'balance_after' => $tx->balance_after,
            ], user: $u);

            return $tx;
        });
    }

    /**
     * Switch plan instantly (demo: no prorating). Price of the new plan is charged from the wallet;
     * a fresh monthly period with a full quota starts today.
     *
     * @throws InsufficientBalance
     */
    public function changePlan(User $u, Plan $plan): Subscription
    {
        return DB::transaction(function () use ($u, $plan) {
            $wallet = $this->lockWallet($u);
            $current = $this->lockSubscription($u);

            if ($current && $current->plan_id === $plan->id) {
                return $current;
            }

            if ($plan->price > 0) {
                if ($wallet->balance < $plan->price) {
                    throw InsufficientBalance::forAmount((int) $plan->price, (int) $wallet->balance);
                }
                $tx = $this->record($wallet, 'subscription', -$plan->price, [
                    'description' => "Tarif: {$plan->name} (1 oy)",
                ]);
            }

            if ($current) {
                $current->update(['period_end' => now()->subDay()->toDateString()]);
            }

            $sub = $this->openPeriod($u, $plan);

            $this->audit->log(AuditAction::Charge, $sub, [
                'plan' => $plan->code,
                'amount' => (int) $plan->price,
                'previous_plan' => $current?->plan->code,
                'balance_after' => isset($tx) ? $tx->balance_after : (int) $wallet->balance,
            ], user: $u);

            return $sub;
        });
    }

    /** @return array{plan: ?Plan, used_requests:int, included_requests:int, used_ai:int, included_ai:int, period_end: ?string} */
    public function usage(User $u): array
    {
        $sub = $this->currentSubscription($u);

        return [
            'plan' => $sub?->plan,
            'used_requests' => (int) ($sub->used_requests ?? 0),
            'included_requests' => (int) ($sub?->plan->included_requests ?? 0),
            'used_ai' => (int) ($sub->used_ai ?? 0),
            'included_ai' => (int) ($sub?->plan->included_ai ?? 0),
            'period_end' => $sub?->period_end?->toDateString(),
        ];
    }

    // ------------------------------------------------------------------

    /** Charged = more charge rows than refund rows for this request. */
    private function isCharged(DataRequest $r): bool
    {
        $counts = Transaction::where('data_request_id', $r->id)
            ->whereIn('type', ['charge', 'refund'])
            ->selectRaw('type, count(*) as c')
            ->groupBy('type')
            ->pluck('c', 'type');

        return (int) ($counts['charge'] ?? 0) > (int) ($counts['refund'] ?? 0);
    }

    private function lockWallet(User $u): Wallet
    {
        $id = $this->walletFor($u)->id;

        return Wallet::whereKey($id)->lockForUpdate()->firstOrFail();
    }

    private function lockSubscription(User $u): ?Subscription
    {
        $sub = $this->currentSubscription($u);

        return $sub ? Subscription::with('plan')->whereKey($sub->id)->lockForUpdate()->first() : null;
    }

    private function openPeriod(User $u, Plan $plan): Subscription
    {
        $start = Carbon::today();

        $sub = Subscription::create([
            'user_id' => $u->id,
            'plan_id' => $plan->id,
            'period_start' => $start->toDateString(),
            'period_end' => $start->copy()->addMonthNoOverflow()->subDay()->toDateString(),
            'used_requests' => 0,
            'used_ai' => 0,
        ]);

        return $sub->setRelation('plan', $plan);
    }

    /** Mutates the (locked) wallet balance and writes the ledger row. */
    private function record(Wallet $wallet, string $type, int $amount, array $attrs = []): Transaction
    {
        if ($amount !== 0) {
            $wallet->balance += $amount;
            $wallet->save();
        }

        return Transaction::create([
            'wallet_id' => $wallet->id,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $wallet->balance,
            'status' => 'completed',
            ...$attrs,
        ]);
    }
}
