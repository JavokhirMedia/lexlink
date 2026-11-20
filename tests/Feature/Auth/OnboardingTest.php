<?php

namespace Tests\Feature\Auth;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Auth\Concerns\FakesGovDataProvider;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use FakesGovDataProvider, RefreshDatabase;

    private const PINFL = '31204851234567';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->fakeGov();
        $this->user = User::factory()->unonboarded()->create(['pinfl' => self::PINFL]);
        Plan::create(['code' => 'start', 'name' => 'Start', 'audience' => 'Yangi', 'price' => 0, 'included_requests' => 3]);
    }

    public function test_role_page_renders(): void
    {
        $this->actingAs($this->user)->get(route('onboarding.show'))->assertRedirect(route('onboarding.role'));
        $this->actingAs($this->user)->get(route('onboarding.role'))
            ->assertOk()->assertInertia(fn ($p) => $p->component('onboarding/role'));
    }

    public function test_choosing_lawyer_finishes_onboarding_with_wallet_and_plan(): void
    {
        $this->actingAs($this->user)
            ->post(route('onboarding.role.store'), ['role' => 'lawyer'])
            ->assertRedirect(route('dashboard'));

        $this->user->refresh();
        $this->assertSame(UserRole::Lawyer, $this->user->role);
        $this->assertSame(0, (int) $this->user->wallet->balance);
        $this->assertSame('start', $this->user->subscriptions()->first()->plan->code);
    }

    public function test_cannot_self_assign_admin(): void
    {
        $this->actingAs($this->user)
            ->post(route('onboarding.role.store'), ['role' => 'admin'])
            ->assertSessionHasErrors('role');

        $this->assertNull($this->user->fresh()->role);
    }

    public function test_choosing_advocate_leads_to_licence_step(): void
    {
        $this->actingAs($this->user)
            ->post(route('onboarding.role.store'), ['role' => 'advocate'])
            ->assertRedirect(route('onboarding.license'));

        $this->assertNull($this->user->fresh()->role);
    }

    public function test_active_licence_of_same_person_makes_user_advocate(): void
    {
        $this->gov->licenses['AL-100'] = $this->licenseRecord('AL-100', self::PINFL);

        $this->actingAs($this->user)
            ->post(route('onboarding.license.store'), ['license_number' => 'al-100'])
            ->assertRedirect(route('dashboard'));

        $this->user->refresh();
        $this->assertSame(UserRole::Advocate, $this->user->role);
        $this->assertSame('AL-100', $this->user->advocateProfile->license_number);
        $this->assertSame('active', $this->user->advocateProfile->license_status);
        $this->assertTrue($this->user->hasActiveLicense());
        $this->assertNotNull($this->user->wallet);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $this->user->id, 'action' => AuditAction::LicenseVerified->value]);
    }

    public function test_licence_of_another_person_is_rejected(): void
    {
        $this->gov->licenses['AL-200'] = $this->licenseRecord('AL-200', '49999999999999');

        $this->actingAs($this->user)
            ->post(route('onboarding.license.store'), ['license_number' => 'AL-200'])
            ->assertSessionHasErrors(['license_number' => 'Litsenziya boshqa shaxsga tegishli']);

        $this->assertNull($this->user->fresh()->role);
        $this->assertNull($this->user->advocateProfile);
    }

    public function test_unknown_licence_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->post(route('onboarding.license.store'), ['license_number' => 'NOPE'])
            ->assertSessionHasErrors('license_number');

        $this->assertNull($this->user->fresh()->role);
    }

    public function test_revoked_licence_is_refused_and_user_can_continue_as_lawyer(): void
    {
        $this->gov->licenses['AL-300'] = $this->licenseRecord('AL-300', self::PINFL, 'revoked');

        $this->actingAs($this->user)
            ->post(route('onboarding.license.store'), ['license_number' => 'AL-300'])
            ->assertRedirect(route('onboarding.license'))
            ->assertSessionHas('license_refusal.status', 'revoked');

        $this->assertNull($this->user->fresh()->role);
        $this->assertNull($this->user->fresh()->advocateProfile);

        $this->actingAs($this->user)
            ->post(route('onboarding.role.store'), ['role' => 'lawyer'])
            ->assertRedirect(route('dashboard'));

        $this->assertSame(UserRole::Lawyer, $this->user->fresh()->role);
    }

    public function test_registry_outage_shows_error(): void
    {
        $this->gov->down = true;

        $this->actingAs($this->user)
            ->post(route('onboarding.license.store'), ['license_number' => 'AL-1'])
            ->assertSessionHasErrors('license_number');
    }

    public function test_lawyer_can_later_upgrade_to_advocate(): void
    {
        $lawyer = User::factory()->lawyer()->create(['pinfl' => '30101011234567']);
        $this->gov->licenses['AL-400'] = $this->licenseRecord('AL-400', '30101011234567');

        $this->actingAs($lawyer)->get(route('onboarding.license'))->assertOk();
        $this->actingAs($lawyer)->post(route('onboarding.license.store'), ['license_number' => 'AL-400'])
            ->assertRedirect(route('dashboard'));

        $this->assertSame(UserRole::Advocate, $lawyer->fresh()->role);
    }

    public function test_onboarded_users_skip_role_page(): void
    {
        $this->actingAs(User::factory()->lawyer()->create())
            ->get(route('onboarding.role'))
            ->assertRedirect(route('dashboard'));
    }
}
