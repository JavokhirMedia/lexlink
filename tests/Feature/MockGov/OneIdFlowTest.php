<?php

namespace Tests\Feature\MockGov;

use App\Models\Mock\MockPerson;
use Database\Seeders\MockGovSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class OneIdFlowTest extends TestCase
{
    use RefreshDatabase;

    private const REDIRECT = 'http://localhost/auth/oneid/callback';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MockGovSeeder::class);
    }

    private function authorizeUrl(array $extra = []): string
    {
        return '/mock-gov/v1/oneid/authorize?'.http_build_query([
            'client_id' => config('lexlink.mock_gov.oneid_client_id'),
            'redirect_uri' => self::REDIRECT,
            'state' => 'xyz',
        ] + $extra);
    }

    private function codeFor(string $pinfl): string
    {
        $location = $this->get($this->authorizeUrl(['login_hint' => $pinfl]))->assertRedirect()->headers->get('Location');
        $this->assertStringStartsWith(self::REDIRECT.'?', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $q);
        $this->assertSame('xyz', $q['state']);

        return $q['code'];
    }

    private function token(string $code, ?string $secret = null): TestResponse
    {
        return $this->post('/mock-gov/v1/oneid/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => self::REDIRECT,
            'client_id' => config('lexlink.mock_gov.oneid_client_id'),
            'client_secret' => $secret ?? config('lexlink.mock_gov.oneid_client_secret'),
        ]);
    }

    public function test_code_token_userinfo_flow(): void
    {
        $code = $this->codeFor(MockGovSeeder::ADVOCATE);

        $token = $this->token($code)->assertOk()->assertJsonPath('token_type', 'Bearer')->json('access_token');

        $this->getJson('/mock-gov/v1/oneid/userinfo', ['Authorization' => "Bearer {$token}"])->assertOk()
            ->assertJson([
                'sub' => MockPerson::oneIdSubFor(MockGovSeeder::ADVOCATE),
                'pinfl' => MockGovSeeder::ADVOCATE,
                'first_name' => 'Dilnoza',
                'last_name' => 'Rustamova',
                'middle_name' => 'Akmalovna',
                'full_name' => 'Rustamova Dilnoza Akmalovna',
                'birth_date' => '1988-05-15',
            ]);

        // code is single use
        $this->token($code)->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
    }

    public function test_bad_client_secret_and_bad_token(): void
    {
        $code = $this->codeFor(MockGovSeeder::LAWYER);
        $this->token($code, 'nope')->assertUnauthorized()->assertJsonPath('error', 'invalid_client');
        $this->getJson('/mock-gov/v1/oneid/userinfo', ['Authorization' => 'Bearer nope'])->assertUnauthorized();
    }

    public function test_expired_code_is_rejected(): void
    {
        $code = $this->codeFor(MockGovSeeder::LAWYER);
        Carbon::setTestNow(now()->addMinutes(6));
        $this->token($code)->assertStatus(400);
    }

    public function test_picker_page_without_login_hint(): void
    {
        $this->get($this->authorizeUrl())->assertOk()
            ->assertSee('Rustamova Dilnoza Akmalovna')
            ->assertSee('Toshpulatov Jasur Baxtiyorovich')
            ->assertSee('login_hint='.MockGovSeeder::ADVOCATE, false);

        $this->get('/mock-gov/v1/oneid/authorize?client_id=evil&redirect_uri='.urlencode(self::REDIRECT))->assertStatus(400);
    }
}
