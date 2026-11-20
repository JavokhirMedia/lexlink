<?php

namespace Tests\Feature\Auth;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\DemoPersonas;
use App\Support\Pinfl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Auth\Concerns\FakesGovDataProvider;
use Tests\TestCase;

class OneIdLoginTest extends TestCase
{
    use FakesGovDataProvider, RefreshDatabase;

    private const PINFL = '31204851234567';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->fakeGov();
    }

    public function test_login_page_shows_oneid_button(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('auth/login')->has('personas'));
    }

    public function test_demo_personas_cover_roles_and_new_users(): void
    {
        $person = fn (string $pinfl, string $last) => DB::table('mock_persons')->insert([
            'pinfl' => $pinfl, 'last_name' => $last, 'first_name' => 'Ism', 'middle_name' => null,
            'birth_date' => '1990-01-01', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $license = fn (string $pinfl, string $no, string $status) => DB::table('mock_advocate_licenses')->insert([
            'license_number' => $no, 'pinfl' => $pinfl, 'full_name' => 'X', 'status' => $status,
            'issued_at' => '2015-01-01', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $person('30000000000001', 'Advokatov');
        $license('30000000000001', 'AL-1', 'active');
        User::factory()->advocate('active', 'AL-1')->create(['pinfl' => '30000000000001']);
        $person('30000000000002', 'Bekorov');
        $license('30000000000002', 'AL-2', 'revoked');
        $person('30000000000003', 'Yuristov');
        User::factory()->lawyer()->create(['pinfl' => '30000000000003']);
        $person('30000000000004', 'Adminov');
        User::factory()->admin()->create(['pinfl' => '30000000000004']);
        $person('30000000000005', 'Yangiyev');

        $groups = collect(DemoPersonas::all())->pluck('group', 'pinfl');

        $this->assertSame('advocate_active', $groups['30000000000001']);
        $this->assertSame('advocate_revoked', $groups['30000000000002']);
        $this->assertSame('lawyer', $groups['30000000000003']);
        $this->assertSame('admin', $groups['30000000000004']);
        $this->assertSame('new', $groups['30000000000005']);
        $this->assertSame('3***********01', DemoPersonas::all()[0]['hint']);
    }

    public function test_password_registration_and_reset_routes_are_gone(): void
    {
        $this->post('/login', ['email' => 'a@b.c', 'password' => 'x'])->assertStatus(405);
        $this->get('/register')->assertNotFound();
        $this->get('/forgot-password')->assertNotFound();
        $this->get('/settings/security')->assertNotFound();
    }

    public function test_redirect_sends_user_to_oneid_authorize_with_state_and_login_hint(): void
    {
        $response = $this->get(route('oneid.redirect', ['login_hint' => self::PINFL]));

        $location = $response->assertRedirect()->headers->get('Location');
        $this->assertStringContainsString('/mock-gov/v1/oneid/authorize?', $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame(config('lexlink.mock_gov.oneid_client_id'), $query['client_id']);
        $this->assertSame(route('oneid.callback'), $query['redirect_uri']);
        $this->assertSame(self::PINFL, $query['login_hint']);
        $this->assertSame(session('oneid_state'), $query['state']);
    }

    public function test_invalid_login_hint_is_dropped(): void
    {
        $location = $this->get(route('oneid.redirect', ['login_hint' => 'abc']))->headers->get('Location');

        $this->assertStringNotContainsString('login_hint', $location);
    }

    public function test_callback_creates_new_user_and_sends_to_onboarding(): void
    {
        $this->gov->oneIdUsers['code-1'] = $this->oneIdPerson(self::PINFL);

        $this->withSession(['oneid_state' => 'st'])
            ->get(route('oneid.callback', ['code' => 'code-1', 'state' => 'st']))
            ->assertRedirect(route('onboarding.show'));

        $user = User::firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->role);
        $this->assertSame('Karimov Aziz Botirovich', $user->name);
        $this->assertSame(self::PINFL, $user->pinfl);
        $this->assertSame(Pinfl::hash(self::PINFL), $user->pinfl_hash);
        $this->assertSame('+998901112233', $user->phone);
        $this->assertSame('oneid-'.self::PINFL, $user->oneid_sub);

        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => AuditAction::Login->value]);
    }

    public function test_callback_logs_in_existing_user_by_pinfl_and_goes_to_dashboard(): void
    {
        $existing = User::factory()->create(['pinfl' => self::PINFL, 'oneid_sub' => null, 'name' => 'Old name']);
        $this->gov->oneIdUsers['code-2'] = $this->oneIdPerson(self::PINFL);

        $this->withSession(['oneid_state' => 'st'])
            ->get(route('oneid.callback', ['code' => 'code-2', 'state' => 'st']))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($existing);
        $this->assertSame(1, User::count());
        $this->assertSame('Karimov Aziz Botirovich', $existing->fresh()->name);
        $this->assertSame('oneid-'.self::PINFL, $existing->fresh()->oneid_sub);
    }

    public function test_callback_rejects_state_mismatch(): void
    {
        $this->gov->oneIdUsers['code-3'] = $this->oneIdPerson(self::PINFL);

        $this->withSession(['oneid_state' => 'expected'])
            ->get(route('oneid.callback', ['code' => 'code-3', 'state' => 'forged']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('oneid_error');

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_callback_handles_provider_failure(): void
    {
        $this->withSession(['oneid_state' => 'st'])
            ->get(route('oneid.callback', ['code' => 'unknown', 'state' => 'st']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('oneid_error');

        $this->assertGuest();
    }

    public function test_login_rechecks_advocate_licence(): void
    {
        $advocate = User::factory()->advocate('active', 'AL-1')->create(['pinfl' => self::PINFL]);
        $this->gov->licenses['AL-1'] = $this->licenseRecord('AL-1', self::PINFL, 'revoked');
        $this->gov->oneIdUsers['code-4'] = $this->oneIdPerson(self::PINFL);

        $this->withSession(['oneid_state' => 'st'])
            ->get(route('oneid.callback', ['code' => 'code-4', 'state' => 'st']))
            ->assertRedirect(route('dashboard'));

        $this->assertSame('revoked', $advocate->advocateProfile->fresh()->license_status);
        $this->assertTrue(AuditLog::where('action', AuditAction::LicenseRevoked)->exists());
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('audit.index'))->assertRedirect(route('login'));
        $this->get(route('team.index'))->assertRedirect(route('login'));
    }

    public function test_users_without_role_are_sent_to_onboarding(): void
    {
        $this->actingAs(User::factory()->unonboarded()->create())
            ->get(route('dashboard'))
            ->assertRedirect(route('onboarding.show'));
    }

    public function test_logout_is_audited(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('logout'))->assertRedirect('/');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => AuditAction::Logout->value]);
    }
}
