<?php

namespace Tests\Feature\Auth;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\LicenseChecker;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Auth\Concerns\FakesGovDataProvider;
use Tests\TestCase;

class LicenseCheckerTest extends TestCase
{
    use FakesGovDataProvider, RefreshDatabase;

    private const PINFL = '31204851234567';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->fakeGov();
    }

    private function advocate(string $status = 'active'): User
    {
        return User::factory()->advocate($status, 'AL-1')->create(['pinfl' => self::PINFL]);
    }

    public function test_active_licence_stays_active(): void
    {
        $user = $this->advocate();
        $this->gov->licenses['AL-1'] = $this->licenseRecord('AL-1', self::PINFL);
        $this->travel(1)->hours();

        $this->assertTrue(app(LicenseChecker::class)->refresh($user));
        $this->assertTrue($user->advocateProfile->fresh()->last_checked_at->isAfter(now()->subMinute()));
        $this->assertSame(0, AuditLog::where('action', AuditAction::LicenseRevoked)->count());
    }

    public function test_revocation_is_detected_and_logged_once(): void
    {
        $user = $this->advocate();
        $this->gov->licenses['AL-1'] = $this->licenseRecord('AL-1', self::PINFL, 'revoked');

        $checker = app(LicenseChecker::class);
        $this->assertFalse($checker->refresh($user));
        $this->assertFalse($checker->refresh($user));

        $this->assertSame('revoked', $user->advocateProfile->fresh()->license_status);
        $this->assertFalse($user->fresh()->hasActiveLicense());
        $this->assertSame(1, AuditLog::where('action', AuditAction::LicenseRevoked)->count());
    }

    public function test_missing_or_foreign_licence_counts_as_revoked(): void
    {
        $user = $this->advocate();
        $this->gov->licenses['AL-1'] = $this->licenseRecord('AL-1', '49999999999999');

        $this->assertFalse(app(LicenseChecker::class)->refresh($user));
        $this->assertSame('revoked', $user->advocateProfile->fresh()->license_status);
    }

    public function test_registry_outage_fails_closed_without_changing_status(): void
    {
        $user = $this->advocate();
        $this->gov->down = true;

        $this->assertFalse(app(LicenseChecker::class)->refresh($user));
        $this->assertSame('active', $user->advocateProfile->fresh()->license_status);
    }

    public function test_non_advocates_are_never_active(): void
    {
        $this->assertFalse(app(LicenseChecker::class)->refresh(User::factory()->lawyer()->create()));
    }

    public function test_recheck_command_updates_all_advocates(): void
    {
        $user = $this->advocate();
        $this->gov->licenses['AL-1'] = $this->licenseRecord('AL-1', self::PINFL, 'suspended');

        $this->artisan('lexlink:recheck-licenses')->assertSuccessful();

        $this->assertSame('suspended', $user->advocateProfile->fresh()->license_status);
    }

    public function test_recheck_command_is_scheduled_daily(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($e) => str_contains((string) $e->command, 'lexlink:recheck-licenses'));

        $this->assertCount(1, $events);
    }
}
