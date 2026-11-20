<?php

namespace Tests\Feature\MockGov;

use App\Http\Controllers\MockGov\RequestController;
use Database\Seeders\MockGovSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MockGovApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MockGovSeeder::class);
    }

    private function key(): array
    {
        return ['X-Api-Key' => config('lexlink.mock_gov.api_key')];
    }

    private function submit(array $overrides = []): array
    {
        $payload = array_replace_recursive([
            'type' => 'salary',
            'subject_pinfl' => MockGovSeeder::DEBTOR,
            'legal_basis' => ['type' => 'court_decision', 'number' => MockGovSeeder::DEMO_DECISION],
            'period' => ['from' => '2026-05', 'to' => '2026-10'],
            'requester_license' => 'AL-00123',
            'params' => [],
        ], $overrides);

        return $this->postJson('/mock-gov/v1/requests', $payload, $this->key())
            ->assertCreated()
            ->assertJsonPath('status', 'accepted')
            ->json();
    }

    private function completeAfterDelay(string $id): array
    {
        Carbon::setTestNow(now()->addSeconds(9));

        return $this->getJson("/mock-gov/v1/requests/{$id}", $this->key())->assertOk()->json();
    }

    public function test_api_key_is_required(): void
    {
        $this->getJson('/mock-gov/v1/advocates/AL-00123')->assertUnauthorized()->assertJsonPath('error', 'unauthorized');
        $this->getJson('/mock-gov/v1/advocates/AL-00123', ['X-Api-Key' => 'wrong'])->assertUnauthorized();
        $this->postJson('/mock-gov/v1/requests', [])->assertUnauthorized();
        $this->getJson('/mock-gov/v1/health')->assertOk()->assertJsonPath('status', 'ok');
    }

    public function test_licence_lookup_active_revoked_and_missing(): void
    {
        $this->getJson('/mock-gov/v1/advocates/AL-00123', $this->key())->assertOk()
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('pinfl', MockGovSeeder::ADVOCATE)
            ->assertJsonPath('full_name', 'Rustamova Dilnoza Akmalovna');

        $this->getJson('/mock-gov/v1/advocates/AL-00087', $this->key())->assertOk()
            ->assertJsonPath('status', 'revoked')
            ->assertJsonPath('revoked_at', '2026-10-01');

        $this->getJson('/mock-gov/v1/advocates/AL-99999', $this->key())->assertNotFound();
    }

    public function test_decision_and_case_lookup(): void
    {
        $this->getJson('/mock-gov/v1/court/decisions/'.MockGovSeeder::DEMO_DECISION, $this->key())->assertOk()
            ->assertJsonPath('subject.pinfl', MockGovSeeder::DEBTOR)
            ->assertJsonPath('allowed_types', ['salary', 'court_case'])
            ->assertJsonPath('expires_at', '2027-05-10')
            ->assertJsonPath('is_valid', true);

        $this->getJson('/mock-gov/v1/court/decisions/2-1001-2502/0133', $this->key())->assertOk()
            ->assertJsonPath('is_valid', false);

        $this->getJson('/mock-gov/v1/court/decisions/2-9999-0000/0000', $this->key())->assertNotFound();

        $this->getJson('/mock-gov/v1/court/cases/'.MockGovSeeder::DEMO_CASE, $this->key())->assertOk()
            ->assertJsonPath('category', 'Aliment undirish')
            ->assertJsonPath('parties.1.pinfl', '3***********12')
            ->assertJsonPath('decisions.0.number', MockGovSeeder::DEMO_DECISION);
    }

    public function test_request_lifecycle_accepted_processing_completed(): void
    {
        Carbon::setTestNow('2026-11-20 07:00:00');
        $res = $this->submit();
        $this->assertMatchesRegularExpression('/^GOV-2026-\d{6}$/', $res['request_id']);

        $this->getJson("/mock-gov/v1/requests/{$res['request_id']}", $this->key())->assertOk()
            ->assertJsonPath('status', 'processing');

        $done = $this->completeAfterDelay($res['request_id']);

        $this->assertSame('completed', $done['status']);
        $this->assertSame('salary', $done['type']);
        $this->assertSame('Karimov A. B.', $done['subject']['full_name']);
        $this->assertSame('3***********12', $done['subject']['pinfl']);
        $this->assertCount(2, $done['data']['employers']);
        $this->assertCount(12, $done['data']['months']); // 6 months x 2 employers
        $this->assertSame(6 * 6_500_000 + 6 * 2_800_000, $done['data']['total']);
        $this->assertSame(9_300_000, $done['data']['average']);
        $this->assertStringEndsWith('+05:00', $done['issued_at']);
        $this->assertSame(RequestController::sign($done['data']), $done['signature']);
        $this->assertSame(64, strlen($done['signature']));

        // stable on re-poll
        $this->getJson("/mock-gov/v1/requests/{$res['request_id']}", $this->key())
            ->assertJsonPath('signature', $done['signature']);
    }

    public function test_property_and_vehicle_results(): void
    {
        $basis = ['legal_basis' => ['type' => 'court_decision', 'number' => '2-1004-2609/0812'], 'subject_pinfl' => MockGovSeeder::PROPERTY_SUBJECT, 'period' => null];

        $p = $this->completeAfterDelay($this->submit(['type' => 'property'] + $basis)['request_id']);
        $this->assertSame('completed', $p['status']);
        $this->assertCount(2, $p['data']['objects']);
        $this->assertSame('Ipoteka', $p['data']['objects'][0]['encumbrances'][0]['type']);

        Carbon::setTestNow();
        $v = $this->completeAfterDelay($this->submit(['type' => 'vehicle'] + $basis)['request_id']);
        $this->assertSame('01 B 777 KA', $v['data']['vehicles'][0]['plate']);
        $this->assertStringEndsWith('******', $v['data']['vehicles'][0]['vin']);
        $this->assertNotNull($v['data']['vehicles'][0]['pledge']);
    }

    public function test_court_case_result(): void
    {
        $r = $this->completeAfterDelay($this->submit(['type' => 'court_case', 'period' => null, 'params' => ['court_case_number' => MockGovSeeder::DEMO_CASE]])['request_id']);
        $this->assertSame('completed', $r['status']);
        $this->assertSame(MockGovSeeder::DEMO_CASE, $r['data']['case_number']);
        $this->assertNotEmpty($r['data']['hearings']);
    }

    /** @return array<string, array{0: array, 1: string}> */
    public static function rejections(): array
    {
        return [
            'decision does not cover vehicle' => [['type' => 'vehicle'], 'ruxsat bermaydi'],
            'unknown decision' => [['legal_basis' => ['number' => '2-9999-0000/0000']], 'topilmadi'],
            'expired decision' => [['subject_pinfl' => '31105700262066', 'legal_basis' => ['number' => '2-1001-2502/0133']], 'muddati'],
            'decision for another subject' => [['subject_pinfl' => MockGovSeeder::CLAIMANT], 'tegishli emas'],
            'revoked licence' => [['requester_license' => 'AL-00087'], "to'xtatilgan"],
            'salary without court decision' => [['legal_basis' => ['type' => 'advocate_order', 'number' => 'ORD-2026/015']], 'soliq siri'],
        ];
    }

    #[DataProvider('rejections')]
    public function test_agency_side_rejection(array $overrides, string $reasonFragment): void
    {
        $res = $this->submit($overrides);

        $this->getJson("/mock-gov/v1/requests/{$res['request_id']}", $this->key())->assertJsonPath('status', 'processing');

        $done = $this->completeAfterDelay($res['request_id']);
        $this->assertSame('rejected', $done['status']);
        $this->assertStringContainsStringIgnoringCase($reasonFragment, $done['rejection_reason']);
        $this->assertArrayNotHasKey('data', $done);
    }

    public function test_advocate_order_is_accepted_for_vehicle(): void
    {
        $done = $this->completeAfterDelay($this->submit([
            'type' => 'vehicle',
            'legal_basis' => ['type' => 'advocate_order', 'number' => 'ORD-2026/015'],
            'period' => null,
        ])['request_id']);

        $this->assertSame('completed', $done['status']);
        $this->assertSame('01 A 123 BC', $done['data']['vehicles'][0]['plate']);
    }

    public function test_invalid_payload_is_422(): void
    {
        $this->postJson('/mock-gov/v1/requests', ['type' => 'passport'], $this->key())
            ->assertUnprocessable()->assertJsonPath('error', 'validation_failed');
        $this->getJson('/mock-gov/v1/requests/GOV-2026-999999', $this->key())->assertNotFound();
    }
}
