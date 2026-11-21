<?php

namespace Tests\Feature\Orders;

use App\Models\CaseMember;
use App\Models\LegalCase;
use App\Models\Mock\MockAdvocateOrder;
use App\Models\User;
use App\Services\Ai\AiService;
use App\Services\Gov\GovDataProvider;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Requests\FakeAiService;

trait OrdersTestHelpers
{
    protected const CLIENT_PINFL = '42303910261025';

    protected const SUBJECT_PINFL = '31207870261012';

    protected OrderRegistryGov $gov;

    protected function bootOrders(): void
    {
        $this->gov = new OrderRegistryGov;
        $this->app->instance(GovDataProvider::class, $this->gov);
        $this->app->instance(AiService::class, new FakeAiService);
        Storage::fake('local');
        $this->withoutVite();
    }

    protected function advocateUser(string $licence = 'AL-00123', string $status = 'active'): User
    {
        return User::factory()->advocate($status, $licence)->create(['name' => 'Rustamova Dilnoza Akmalovna']);
    }

    protected function caseOf(User $advocate, ?string $courtCaseNumber = null): LegalCase
    {
        $case = LegalCase::create([
            'owner_id' => $advocate->id,
            'title' => 'Aliment undirish — Karimova N.',
            'client_name' => 'Karimova Nilufar Shavkatovna',
            'court_case_number' => $courtCaseNumber,
            'category' => 'aliment',
            'status' => 'active',
        ]);
        CaseMember::create(['case_id' => $case->id, 'user_id' => $advocate->id, 'role' => 'advocate', 'can_view_responses' => true, 'secrecy_declared_at' => now()]);

        return $case;
    }

    protected function registryOrder(string $number, string $licence = 'AL-00123', string $status = 'active', string $clientPinfl = self::CLIENT_PINFL): MockAdvocateOrder
    {
        return MockAdvocateOrder::create([
            'order_number' => $number,
            'license_number' => $licence,
            'advocate_name' => 'Rustamova Dilnoza Akmalovna',
            'client_pinfl' => $clientPinfl,
            'client_name' => 'Karimova Nilufar Shavkatovna',
            'matter' => 'Aliment undirish',
            'issued_at' => now()->subDays(10)->toDateString(),
            'status' => $status,
        ]);
    }

    /** Add an advocate-order basis through the HTTP form. */
    protected function addOrderBasis(User $advocate, LegalCase $case, string $number, array $types = ['property', 'vehicle'], array $override = [])
    {
        return $this->actingAs($advocate)->post(route('cases.bases.store', $case), array_replace([
            'type' => 'advocate_order',
            'number' => $number,
            'subject_pinfl' => self::SUBJECT_PINFL,
            'subject_name' => 'Karimov Aziz Botirovich',
            'allowed_types' => $types,
            'expires_at' => now()->addMonths(3)->toDateString(),
            'purpose' => "Aliment undirish ishi bo'yicha javobgarning mol-mulkini aniqlash",
        ], $override));
    }
}
