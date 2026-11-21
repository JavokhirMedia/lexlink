<?php

namespace Tests\Feature\Orders;

use App\Enums\DataRequestStatus;
use App\Enums\LegalBasisType;
use App\Jobs\FetchGovResultJob;
use App\Models\LegalBasis;
use App\Models\LegalCase;
use App\Models\User;
use App\Services\DataRequestService;
use App\Support\Pinfl;
use Database\Seeders\AdvocateOrdersSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoUsersSeeder;
use Database\Seeders\MockGovSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pitch story: "sud ajrimi avtotransportni qamramaydi, lekin advokat orderi bilan qonuniy so'rash mumkin".
 */
class DemoOrderPathTest extends TestCase
{
    use OrdersTestHelpers;
    use RefreshDatabase;

    public function test_alimony_case_vehicle_via_advocate_order(): void
    {
        $this->bootOrders();
        $this->seed(DatabaseSeeder::class);

        $advocate = User::where('pinfl_hash', Pinfl::hash(MockGovSeeder::ADVOCATE))->firstOrFail();
        $case = LegalCase::where('title', DemoUsersSeeder::CASE_TITLE)->firstOrFail();
        $svc = app(DataRequestService::class);

        // 1. The court decision alone does NOT cover the vehicle.
        $this->actingAs($advocate)->post(route('cases.bases.store', $case), ['type' => 'court_decision', 'number' => MockGovSeeder::DEMO_DECISION]);
        $this->assertSame('verified', LegalBasis::where('number', MockGovSeeder::DEMO_DECISION)->value('verification_status'));
        $vehicle = $svc->create($case, $advocate, [
            'type' => 'vehicle', 'subject_pinfl' => MockGovSeeder::DEBTOR, 'subject_name' => 'Karimov Aziz Botirovich',
            'purpose' => 'Undiruv qaratiladigan mol-mulkni aniqlash', 'request_text' => "Rasmiy advokat so'rovi",
        ]);
        $this->assertFalse($svc->checkBasis($vehicle)['ok']);
        $this->assertStringContainsString("avtotransport ma'lumotini qamramaydi", $svc->checkBasis($vehicle)['reason']);

        // 2. Advocate adds e-order ORD-2026-000457 (subject Karimov Aziz, property + vehicle).
        $this->addOrderBasis($advocate, $case, AdvocateOrdersSeeder::DEMO_ORDER)->assertSessionHasNoErrors();
        $order = LegalBasis::where('number', AdvocateOrdersSeeder::DEMO_ORDER)->firstOrFail();
        $this->assertSame(LegalBasisType::AdvocateOrder, $order->type);
        $this->assertSame('verified', $order->verification_status);
        $this->assertSame('Karimova Nilufar Shavkatovna', $order->verification_payload['client_name']);

        // 3. Vehicle request is now covered by the order -> sign & submit -> completed.
        $vehicle = $svc->update($vehicle, []);
        $this->assertTrue($svc->checkBasis($vehicle)['ok']);
        $this->assertSame($order->id, $svc->checkBasis($vehicle)['basis']->id);

        $this->actingAs($advocate)->post(route('requests.sign', $vehicle), ['key_id' => 'demo-eri', 'confirm' => true])->assertSessionHasNoErrors();
        $vehicle->refresh();
        // the demo case number exists in the court registry => proceedings started => 3 ish kuni
        $this->assertSame('proceedings', $vehicle->deadline_basis);
        $this->assertSame(now()->addWeekdays(3)->toDateString(), $vehicle->due_at->toDateString());

        $this->travel(5)->seconds();
        FetchGovResultJob::dispatchSync($vehicle->id);
        $this->assertSame(DataRequestStatus::Completed, $vehicle->fresh()->status);

        // 4. The seeded client link exists for the pitch.
        $this->assertDatabaseHas('case_client_links', ['token' => AdvocateOrdersSeeder::DEMO_TOKEN, 'case_id' => $case->id, 'order_number' => AdvocateOrdersSeeder::DEMO_ORDER]);
    }
}
