<?php

namespace Tests\Feature\Orders;

use App\Enums\AuditAction;
use App\Enums\DataRequestType;
use App\Enums\LegalBasisType;
use App\Models\AuditLog;
use App\Models\LegalBasis;
use App\Services\LegalBasisVerifier;
use App\Support\LegalMatrix;
use App\Support\Pinfl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvocateOrderVerificationTest extends TestCase
{
    use OrdersTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootOrders();
    }

    public function test_order_types_come_from_the_legal_matrix(): void
    {
        $this->assertSame(LegalMatrix::orderTypes(), LegalBasisVerifier::advocateOrderTypes());
        foreach (['salary', 'bank_accounts', 'border_crossing'] as $courtOnly) {
            $this->assertNotContains($courtOnly, LegalBasisVerifier::advocateOrderTypes());
        }
    }

    public function test_registry_order_of_this_advocate_is_verified_with_client_data(): void
    {
        $adv = $this->advocateUser();
        $case = $this->caseOf($adv);
        $this->registryOrder('ORD-2026-000457');

        $this->addOrderBasis($adv, $case, 'ord-2026-000457')->assertSessionHasNoErrors();

        $b = LegalBasis::firstOrFail();
        $this->assertSame('ORD-2026-000457', $b->number);
        $this->assertSame('verified', $b->verification_status);
        $this->assertSame(['property', 'vehicle'], $b->allowed_types);
        $this->assertSame(Pinfl::hash(self::SUBJECT_PINFL), $b->subject_pinfl_hash);
        $this->assertSame("Aliment undirish ishi bo'yicha javobgarning mol-mulkini aniqlash", $b->purpose);
        $this->assertSame('Karimova Nilufar Shavkatovna', $b->verification_payload['client_name']);
        $this->assertSame(Pinfl::hash(self::CLIENT_PINFL), $b->verification_payload['client_pinfl_hash']);
        $this->assertSame('Aliment undirish', $b->verification_payload['order']['matter']);
        // the raw client PINFL is never stored in the JSON payload
        $this->assertStringNotContainsString(self::CLIENT_PINFL, json_encode($b->verification_payload));
        $this->assertTrue(AuditLog::where('action', AuditAction::LegalBasisVerified)->where('subject_id', $b->id)->exists());

        $this->assertCount(1, app(LegalBasisVerifier::class)->coverage($case)['vehicle']);
    }

    public function test_unknown_order_is_not_found(): void
    {
        $adv = $this->advocateUser();
        $case = $this->caseOf($adv);

        $this->addOrderBasis($adv, $case, 'ORD-2026-000999')->assertSessionHasNoErrors();

        $b = LegalBasis::firstOrFail();
        $this->assertSame('not_found', $b->verification_status);
        $this->assertStringContainsString('elektron orderlar reyestrida ORD-2026-000999 raqamli order topilmadi', $b->verification_payload['error']);
    }

    public function test_other_advocates_order_is_a_mismatch(): void
    {
        $adv = $this->advocateUser('AL-00123');
        $case = $this->caseOf($adv);
        $this->registryOrder('ORD-2026-000398', 'AL-00045');

        $this->addOrderBasis($adv, $case, 'ORD-2026-000398');

        $b = LegalBasis::firstOrFail();
        $this->assertSame('mismatch', $b->verification_status);
        $this->assertStringContainsString('boshqa advokatga (AL-00045) berilgan', $b->verification_payload['error']);
        $this->assertSame([], app(LegalBasisVerifier::class)->coverage($case)['vehicle']);
    }

    public function test_cancelled_order_is_a_mismatch(): void
    {
        $adv = $this->advocateUser();
        $case = $this->caseOf($adv);
        $this->registryOrder('ORD-2026-000312', 'AL-00123', 'cancelled');

        $this->addOrderBasis($adv, $case, 'ORD-2026-000312');

        $b = LegalBasis::firstOrFail();
        $this->assertSame('mismatch', $b->verification_status);
        $this->assertStringContainsString('bekor qilingan', $b->verification_payload['error']);
    }

    public function test_order_cannot_cover_salary_bank_or_border(): void
    {
        $adv = $this->advocateUser();
        $case = $this->caseOf($adv);
        $this->registryOrder('ORD-2026-000457');

        foreach (['salary', 'bank_accounts', 'border_crossing'] as $type) {
            $this->addOrderBasis($adv, $case, 'ORD-2026-000457', [$type, 'property'])->assertSessionHasErrors('allowed_types.0');
        }
        $this->assertSame(0, LegalBasis::count());

        // Even if a court-only type slips into the model, the verifier strips it and checkBasis never accepts an order for it.
        $b = LegalBasis::create([
            'case_id' => $case->id, 'created_by' => $adv->id, 'type' => LegalBasisType::AdvocateOrder,
            'number' => 'ORD-2026-000457', 'subject_pinfl' => self::SUBJECT_PINFL,
            'allowed_types' => ['salary', 'bank_accounts', 'border_crossing', 'vehicle'],
            'expires_at' => now()->addMonth()->toDateString(),
        ]);
        $this->actingAs($adv);
        app(LegalBasisVerifier::class)->verify($b);

        $this->assertSame('verified', $b->verification_status);
        $this->assertSame(['vehicle'], $b->fresh()->allowed_types);
        $coverage = app(LegalBasisVerifier::class)->coverage($case);
        foreach ([DataRequestType::Salary, DataRequestType::BankAccounts, DataRequestType::BorderCrossing] as $t) {
            $this->assertSame([], $coverage[$t->value]);
        }
    }

    public function test_purpose_is_required_for_an_order(): void
    {
        $adv = $this->advocateUser();
        $case = $this->caseOf($adv);
        $this->registryOrder('ORD-2026-000457');

        $this->addOrderBasis($adv, $case, 'ORD-2026-000457', override: ['purpose' => ''])->assertSessionHasErrors('purpose');
        $this->assertSame(0, LegalBasis::count());
    }

    public function test_issue_order_button_creates_registry_order_and_flashes_number(): void
    {
        $adv = $this->advocateUser();
        $case = $this->caseOf($adv);

        $this->actingAs($adv)->post(route('cases.advocate-orders.store', $case), [
            'client_pinfl' => self::CLIENT_PINFL,
            'client_name' => 'Karimova Nilufar Shavkatovna',
            'matter' => 'Aliment undirish',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('mock_advocate_orders', ['license_number' => 'AL-00123', 'client_pinfl' => self::CLIENT_PINFL]);
        $this->assertTrue(AuditLog::where('action', AuditAction::AdvocateOrderIssued)->where('case_id', $case->id)->exists());
    }
}
