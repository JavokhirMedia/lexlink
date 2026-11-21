<?php

namespace Tests\Feature\Orders;

use App\Models\Mock\MockAdvocateLicense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvocateOrderRegistryApiTest extends TestCase
{
    use OrdersTestHelpers;
    use RefreshDatabase;

    private array $key = ['X-Api-Key' => 'mock-gov-demo-key'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['lexlink.mock_gov.api_key' => 'mock-gov-demo-key']);
    }

    public function test_get_order_requires_api_key_and_returns_the_order(): void
    {
        $this->registryOrder('ORD-2026-000457');

        $this->getJson('/mock-gov/v1/advocate-orders/ORD-2026-000457')->assertUnauthorized();

        $this->getJson('/mock-gov/v1/advocate-orders/ord-2026-000457', $this->key)
            ->assertOk()
            ->assertJsonPath('order_number', 'ORD-2026-000457')
            ->assertJsonPath('license_number', 'AL-00123')
            ->assertJsonPath('client.full_name', 'Karimova Nilufar Shavkatovna')
            ->assertJsonPath('client.pinfl_masked', '4***********25')
            ->assertJsonPath('matter', 'Aliment undirish')
            ->assertJsonPath('status', 'active');

        $this->getJson('/mock-gov/v1/advocate-orders/ORD-2026-999999', $this->key)->assertNotFound()->assertJsonPath('error', 'not_found');
    }

    public function test_post_issues_a_new_order_for_an_active_licence_only(): void
    {
        MockAdvocateLicense::create(['license_number' => 'AL-00123', 'pinfl' => '41505880261034', 'full_name' => 'Rustamova Dilnoza Akmalovna', 'status' => 'active', 'issued_at' => '2014-06-10']);
        MockAdvocateLicense::create(['license_number' => 'AL-00087', 'pinfl' => '30311790261058', 'full_name' => 'Xolmatov Sherzod', 'status' => 'revoked', 'issued_at' => '2010-01-01']);

        $body = ['license_number' => 'AL-00123', 'client_pinfl' => self::CLIENT_PINFL, 'client_name' => 'Karimova Nilufar Shavkatovna', 'matter' => 'Aliment undirish'];

        $res = $this->postJson('/mock-gov/v1/advocate-orders', $body, $this->key)->assertCreated()
            ->assertJsonPath('license_number', 'AL-00123')
            ->assertJsonPath('advocate_name', 'Rustamova Dilnoza Akmalovna')
            ->assertJsonPath('status', 'active');
        $this->assertMatchesRegularExpression('/^ORD-\d{4}-\d{6}$/', $res->json('order_number'));
        $this->assertDatabaseHas('mock_advocate_orders', ['order_number' => $res->json('order_number'), 'client_pinfl' => self::CLIENT_PINFL]);

        $this->postJson('/mock-gov/v1/advocate-orders', ['license_number' => 'AL-00087'] + $body, $this->key)->assertStatus(422)->assertJsonPath('error', 'license_inactive');
        $this->postJson('/mock-gov/v1/advocate-orders', ['client_pinfl' => '123'] + $body, $this->key)->assertStatus(422);
    }
}
