<?php

namespace Tests\Feature\Orders;

use App\Http\Controllers\MockGov\AdvocateOrderController;
use App\Http\Controllers\MockGov\RegistryController;
use App\Models\Mock\MockAdvocateOrder;
use App\Models\Mock\MockCourtCase;
use App\Models\Mock\MockCourtDecision;
use Tests\Feature\Requests\FakeGovProvider;

/** FakeGovProvider + the mock e-order / court registries read straight from the mock tables. */
class OrderRegistryGov extends FakeGovProvider
{
    public function advocateOrder(string $number): ?array
    {
        $row = MockAdvocateOrder::where('order_number', strtoupper(trim($number)))->first();

        return $row ? AdvocateOrderController::payload($row) : null;
    }

    public function issueAdvocateOrder(array $data): array
    {
        $row = MockAdvocateOrder::create([
            'order_number' => sprintf('ORD-2026-%06d', 900 + MockAdvocateOrder::count() + 1),
            'license_number' => $data['license_number'],
            'advocate_name' => 'Test advokat',
            'client_pinfl' => $data['client_pinfl'],
            'client_name' => $data['client_name'],
            'matter' => $data['matter'],
            'issued_at' => now()->toDateString(),
            'status' => 'active',
        ]);

        return AdvocateOrderController::payload($row);
    }

    public function courtCase(string $number): ?array
    {
        $row = MockCourtCase::where('case_number', trim($number))->first();

        return $row ? RegistryController::casePayload($row) : null;
    }

    public function courtDecision(string $number): ?array
    {
        $row = MockCourtDecision::where('number', trim($number))->first();

        return $row ? RegistryController::decisionPayload($row) : null;
    }
}
