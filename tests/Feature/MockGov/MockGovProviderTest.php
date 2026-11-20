<?php

namespace Tests\Feature\MockGov;

use App\Services\Gov\Dto\GovSubmission;
use App\Services\Gov\GovApiException;
use App\Services\Gov\GovDataProvider;
use App\Services\Gov\MockGovProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MockGovProviderTest extends TestCase
{
    private const BASE = 'http://gov.test/mock-gov/v1';

    protected function setUp(): void
    {
        parent::setUp();
        config(['lexlink.mock_gov.base_url' => self::BASE, 'lexlink.mock_gov.api_key' => 'k-123']);
    }

    private function provider(): GovDataProvider
    {
        return app(GovDataProvider::class);
    }

    public function test_interface_is_bound_to_mock_provider(): void
    {
        $this->assertInstanceOf(MockGovProvider::class, $this->provider());
    }

    public function test_lookups_send_api_key_and_return_null_on_404(): void
    {
        Http::fake([
            self::BASE.'/advocates/AL-00123' => Http::response(['license_number' => 'AL-00123', 'status' => 'active']),
            self::BASE.'/advocates/*' => Http::response(['error' => 'not_found'], 404),
            self::BASE.'/court/decisions/2-1001-2611/0457' => Http::response(['number' => '2-1001-2611/0457', 'allowed_types' => ['salary']]),
            self::BASE.'/court/cases/*' => Http::response([], 404),
        ]);

        $this->assertSame('active', $this->provider()->advocateLicense('AL-00123')['status']);
        $this->assertNull($this->provider()->advocateLicense('AL-99999'));
        $this->assertSame(['salary'], $this->provider()->courtDecision('2-1001-2611/0457')['allowed_types']);
        $this->assertNull($this->provider()->courtCase('2-0000-0000/0000'));

        Http::assertSent(fn (Request $r) => $r->hasHeader('X-Api-Key', 'k-123'));
    }

    public function test_submit_status_result(): void
    {
        Http::fake([
            self::BASE.'/requests' => Http::response(['request_id' => 'GOV-2026-000001', 'status' => 'accepted'], 201),
            self::BASE.'/requests/GOV-2026-000001' => Http::sequence()
                ->push(['request_id' => 'GOV-2026-000001', 'status' => 'processing'])
                ->push(['request_id' => 'GOV-2026-000001', 'status' => 'completed', 'data' => ['total' => 1]]),
        ]);

        $res = $this->provider()->submit(new GovSubmission('salary', '31207870261012', ['type' => 'court_decision', 'number' => '2-1001-2611/0457'], ['from' => '2026-05', 'to' => '2026-10'], 'AL-00123'));
        $this->assertSame('GOV-2026-000001', $res['request_id']);
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r['subject_pinfl'] === '31207870261012' && $r['requester_license'] === 'AL-00123');

        $this->assertSame(['request_id' => 'GOV-2026-000001', 'status' => 'processing'], $this->provider()->status('GOV-2026-000001'));
        $this->assertSame(1, $this->provider()->result('GOV-2026-000001')['data']['total']);
    }

    public function test_result_throws_when_not_completed(): void
    {
        Http::fake([self::BASE.'/requests/*' => Http::response(['status' => 'rejected', 'rejection_reason' => 'x'])]);

        $this->assertSame('x', $this->provider()->status('GOV-1')['rejection_reason']);
        $this->expectException(GovApiException::class);
        $this->provider()->result('GOV-1');
    }

    public function test_server_errors_and_transport_errors_throw(): void
    {
        Http::fake([
            self::BASE.'/advocates/*' => Http::response('boom', 503),
            self::BASE.'/court/decisions/*' => fn () => throw new ConnectionException('refused'),
        ]);

        try {
            $this->provider()->advocateLicense('AL-00123');
            $this->fail('expected exception');
        } catch (GovApiException $e) {
            $this->assertStringContainsString('503', $e->getMessage());
        }

        $this->expectException(GovApiException::class);
        $this->provider()->courtDecision('2-1001-2611/0457');
    }

    public function test_oneid_user_exchanges_code_then_reads_userinfo(): void
    {
        Http::fake([
            self::BASE.'/oneid/token' => Http::response(['access_token' => 'tok', 'token_type' => 'Bearer']),
            self::BASE.'/oneid/userinfo' => Http::response(['sub' => 'oneid-x', 'pinfl' => '41505880261034']),
        ]);

        $user = $this->provider()->oneIdUser('the-code', 'http://localhost/cb');

        $this->assertSame('41505880261034', $user['pinfl']);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/oneid/token') && $r['code'] === 'the-code' && $r['client_secret'] === config('lexlink.mock_gov.oneid_client_secret'));
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/oneid/userinfo') && $r->hasHeader('Authorization', 'Bearer tok'));
    }
}
