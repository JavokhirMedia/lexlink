<?php

namespace Tests\Feature\Billing;

use App\Exceptions\InsufficientBalance;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\Transaction;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingServiceTest extends TestCase
{
    use BillingTestHelpers, RefreshDatabase;

    private BillingService $billing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlans();
        $this->billing = app(BillingService::class);
    }

    public function test_charge_within_quota_uses_subscription_not_wallet(): void
    {
        $u = $this->advocate(balance: 100_000, used: 3);
        $r = $this->dataRequest($u);

        $this->assertSame(['cost' => 0, 'from_subscription' => true], array_intersect_key($this->billing->quoteForRequest($u), ['cost' => 1, 'from_subscription' => 1]));

        $this->billing->chargeForRequest($r, $u);

        $this->assertSame(4, $u->subscriptions()->first()->used_requests);
        $this->assertSame(100_000, (int) $u->wallet->fresh()->balance);
        $this->assertTrue($r->fresh()->charged_from_subscription);
        $this->assertTrue($r->charged_from_subscription);
        $this->assertDatabaseHas('transactions', ['data_request_id' => $r->id, 'type' => 'charge', 'amount' => 0, 'balance_after' => 100_000]);
        $this->assertSame(1, AuditLog::where('action', 'charge')->where('case_id', $r->case_id)->count());

        // charging the same request again is a no-op
        $this->billing->chargeForRequest($r, $u);
        $this->assertSame(4, $u->subscriptions()->first()->used_requests);
    }

    public function test_charge_from_wallet_beyond_quota(): void
    {
        $u = $this->advocate(balance: 100_000, used: 10);
        $r = $this->dataRequest($u);

        $this->assertSame(15_000, $this->billing->quoteForRequest($u)['cost']);

        $this->billing->chargeForRequest($r, $u);

        $this->assertSame(85_000, (int) $u->wallet->fresh()->balance);
        $this->assertSame(10, $u->subscriptions()->first()->used_requests);
        $this->assertSame(15_000, (int) $r->fresh()->cost);
        $this->assertFalse($r->fresh()->charged_from_subscription);
        $this->assertDatabaseHas('transactions', ['data_request_id' => $r->id, 'type' => 'charge', 'amount' => -15_000, 'balance_after' => 85_000]);
    }

    public function test_insufficient_balance_throws_and_changes_nothing(): void
    {
        $u = $this->advocate(balance: 5_000, used: 10);
        $r = $this->dataRequest($u);

        $this->assertFalse($this->billing->quoteForRequest($u)['allowed']);

        try {
            $this->billing->chargeForRequest($r, $u);
            $this->fail('Expected InsufficientBalance');
        } catch (InsufficientBalance $e) {
            $this->assertSame('balance', $e->reason);
            $this->assertSame(15_000, $e->required);
        }

        $this->assertSame(5_000, (int) $u->wallet->fresh()->balance);
        $this->assertSame(0, Transaction::count());
    }

    public function test_start_plan_cannot_submit(): void
    {
        $u = $this->advocate(plan: 'start', balance: 1_000_000);
        $r = $this->dataRequest($u);

        $this->expectException(InsufficientBalance::class);
        $this->expectExceptionMessage("Advokat tarifiga o'ting");

        $this->billing->chargeForRequest($r, $u);
    }

    public function test_refund_from_wallet_is_idempotent(): void
    {
        $u = $this->advocate(balance: 100_000, used: 10);
        $r = $this->dataRequest($u);
        $this->billing->chargeForRequest($r, $u);

        $this->billing->refundForRequest($r);
        $this->billing->refundForRequest($r);

        $this->assertSame(100_000, (int) $u->wallet->fresh()->balance);
        $this->assertSame(1, Transaction::where('type', 'refund')->count());
        $this->assertDatabaseHas('transactions', ['type' => 'refund', 'amount' => 15_000, 'balance_after' => 100_000]);
        $this->assertSame(1, AuditLog::where('action', 'refund')->count());
    }

    public function test_refund_from_quota_is_idempotent(): void
    {
        $u = $this->advocate(balance: 0, used: 2);
        $r = $this->dataRequest($u);
        $this->billing->chargeForRequest($r, $u);
        $this->assertSame(3, $u->subscriptions()->first()->used_requests);

        $this->billing->refundForRequest($r);
        $this->billing->refundForRequest($r);

        $this->assertSame(2, $u->subscriptions()->first()->used_requests);
        $this->assertSame(0, (int) $u->wallet->fresh()->balance);
        $this->assertSame(1, Transaction::where('type', 'refund')->count());
    }

    public function test_refund_without_charge_does_nothing(): void
    {
        $u = $this->advocate(balance: 0);
        $r = $this->dataRequest($u);

        $this->billing->refundForRequest($r);

        $this->assertSame(0, Transaction::count());
    }

    public function test_ai_charge_uses_quota_then_wallet(): void
    {
        config(['lexlink.prices.ai_extra' => 5_000]);
        $u = $this->advocate(balance: 7_000, usedAi: 4); // advocate plan: 5 AI included

        $this->assertSame(0, $this->billing->chargeAi($u, 'draft_claim'));
        $this->assertSame(5, $u->subscriptions()->first()->used_ai);

        $this->assertSame(5_000, $this->billing->chargeAi($u, 'draft_claim'));
        $this->assertSame(2_000, (int) $u->wallet->fresh()->balance);

        $this->expectException(InsufficientBalance::class);
        $this->billing->chargeAi($u, 'draft_claim');
    }

    public function test_topup_credits_wallet_and_is_idempotent_per_external_id(): void
    {
        $u = $this->advocate(balance: 1_000);

        $tx = $this->billing->topup($u, 50_000, 'click', 'ext-1');
        $again = $this->billing->topup($u, 50_000, 'click', 'ext-1');

        $this->assertSame($tx->id, $again->id);
        $this->assertSame(51_000, (int) $u->wallet->fresh()->balance);
        $this->assertSame(1, AuditLog::where('action', 'wallet_topup')->count());
    }

    public function test_change_plan_charges_wallet_and_resets_quota(): void
    {
        $u = $this->advocate(plan: 'start', balance: 200_000);

        $sub = $this->billing->changePlan($u, Plan::where('code', 'advocate')->first());

        $this->assertSame('advocate', $sub->plan->code);
        $this->assertSame(51_000, (int) $u->wallet->fresh()->balance);
        $this->assertSame('advocate', $this->billing->currentSubscription($u)->plan->code);
        $this->assertTrue($this->billing->quoteForRequest($u)['from_subscription']);
    }

    public function test_change_plan_with_low_balance_fails(): void
    {
        $u = $this->advocate(plan: 'start', balance: 1_000);

        $this->post(route('billing.plan'), ['plan' => 'firm'], ['referer' => route('billing.index')])
            ->assertStatus(302); // unauthenticated -> login

        $this->actingAs($u)
            ->from(route('billing.index'))
            ->post(route('billing.plan'), ['plan' => 'firm'])
            ->assertRedirect(route('billing.index'))
            ->assertSessionHasErrors('billing');

        $this->assertSame('start', $this->billing->currentSubscription($u)->plan->code);
    }
}
