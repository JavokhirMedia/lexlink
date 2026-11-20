<?php

namespace Tests\Feature\MockGov;

use App\Models\CaseMember;
use App\Models\LegalCase;
use App\Models\User;
use App\Support\Pinfl;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoUsersSeeder;
use Database\Seeders\MockGovSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_seed_builds_demo_scenario_and_is_rerunnable(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('mock_persons', 30);
        $this->assertDatabaseCount('mock_court_decisions', 10);
        $this->assertDatabaseCount('mock_court_cases', 15);
        $this->assertDatabaseCount('mock_advocate_licenses', 5);
        $this->assertDatabaseCount('plans', 3);
        $this->assertDatabaseCount('users', 4);

        $advocate = User::where('pinfl_hash', Pinfl::hash(MockGovSeeder::ADVOCATE))->firstOrFail();
        $this->assertTrue($advocate->hasActiveLicense());
        $this->assertSame(300000, (int) $advocate->wallet->balance);
        $this->assertSame('advocate', $advocate->activeSubscription->plan->code);

        $revoked = User::where('pinfl_hash', Pinfl::hash(MockGovSeeder::REVOKED))->firstOrFail();
        $this->assertFalse($revoked->hasActiveLicense());

        $case = LegalCase::where('title', DemoUsersSeeder::CASE_TITLE)->firstOrFail();
        $this->assertSame($advocate->id, $case->owner_id);
        $this->assertCount(0, $case->legalBases);

        $lawyer = User::where('pinfl_hash', Pinfl::hash(MockGovSeeder::LAWYER))->firstOrFail();
        $member = CaseMember::where('case_id', $case->id)->where('user_id', $lawyer->id)->firstOrFail();
        $this->assertTrue($member->can_view_responses);
        $this->assertNull($member->secrecy_declared_at);
        $this->assertSame('accepted', $advocate->lawyerLinks()->first()->status);
    }
}
