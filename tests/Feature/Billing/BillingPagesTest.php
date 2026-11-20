<?php

namespace Tests\Feature\Billing;

use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BillingPagesTest extends TestCase
{
    use BillingTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlans();
        $this->withoutVite();
    }

    public function test_billing_index_renders(): void
    {
        $u = $this->advocate(balance: 120_000, used: 3);

        $this->actingAs($u)->get(route('billing.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('billing/index')
                ->has('plans', 3)
                ->where('current_plan_code', 'advocate')
                ->where('usage.used_requests', 3)
                ->where('usage.included_requests', 10)
                ->where('balance', 120_000));
    }

    public function test_checkout_page_validates_provider_and_amount(): void
    {
        $u = $this->advocate();

        $this->actingAs($u)->get(route('billing.checkout', ['provider' => 'payme', 'amount' => 100_000]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('billing/checkout')->where('provider.name', 'Payme')->where('amount', 100_000));

        $this->actingAs($u)->get(route('billing.checkout', ['provider' => 'paypal', 'amount' => 100_000]))->assertSessionHasErrors('provider');
    }

    public function test_topup_through_mock_flow(): void
    {
        $u = $this->advocate(balance: 0);
        $this->actingAs($u);

        $prepare = $this->postJson(route('billing.mock.prepare', 'click'), [
            'amount' => 100_000,
            'card_number' => '8600 0000 0000 0000',
            'expiry' => '12/29',
        ])->assertOk()->assertJsonStructure(['payment_id', 'amount', 'card_masked']);

        $paymentId = $prepare->json('payment_id');

        $this->postJson(route('billing.mock.complete', 'click'), ['payment_id' => $paymentId, 'code' => '12a'])
            ->assertStatus(422)->assertJsonValidationErrors('code');

        $this->postJson(route('billing.mock.complete', 'click'), ['payment_id' => $paymentId, 'code' => '666666'])
            ->assertOk()
            ->assertJson(['status' => 'completed', 'balance' => 100_000, 'redirect' => route('billing.index')]);

        // replaying the confirmation must not credit twice
        $this->postJson(route('billing.mock.complete', 'click'), ['payment_id' => $paymentId, 'code' => '666666'])->assertOk();

        $this->assertSame(100_000, (int) $u->wallet->fresh()->balance);
        $this->assertSame(1, Transaction::where('type', 'topup')->where('provider', 'click')->count());
    }

    public function test_mock_flow_rejects_bad_card_and_foreign_payment(): void
    {
        $u = $this->advocate();
        $this->actingAs($u)->postJson(route('billing.mock.prepare', 'payme'), ['amount' => 50_000, 'card_number' => '8600', 'expiry' => '12/29'])
            ->assertStatus(422)->assertJsonValidationErrors('card_number');

        $id = $this->postJson(route('billing.mock.prepare', 'payme'), ['amount' => 50_000, 'card_number' => '8600000000000000', 'expiry' => '12/29'])->json('payment_id');

        $other = $this->advocate();
        $this->actingAs($other)->postJson(route('billing.mock.complete', 'payme'), ['payment_id' => $id, 'code' => '666666'])
            ->assertStatus(422)->assertJsonValidationErrors('payment_id');
        $this->assertSame(0, Transaction::count());
    }
}
