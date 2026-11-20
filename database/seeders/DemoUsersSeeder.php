<?php

namespace Database\Seeders;

use App\Models\AdvocateLawyerLink;
use App\Models\AdvocateProfile;
use App\Models\CaseMember;
use App\Models\LegalCase;
use App\Models\Mock\MockPerson;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Support\Pinfl;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo scenario ("Demo ssenariy") app users. Requires MockGovSeeder + PlansSeeder first.
 * All users log in via mock OneID (login_hint = PINFL); password "password" also works for e-mail login.
 * The legal basis for the demo case is intentionally NOT created — the advocate enters it live.
 */
class DemoUsersSeeder extends Seeder
{
    public const CASE_TITLE = 'Aliment undirish — Karimova N.';

    public function run(): void
    {
        $advocate = $this->user(MockGovSeeder::ADVOCATE, 'advocate', 'dilnoza.rustamova@demo.lexlink.uz');
        $lawyer = $this->user(MockGovSeeder::LAWYER, 'lawyer', 'jasur.toshpulatov@demo.lexlink.uz');
        $revoked = $this->user(MockGovSeeder::REVOKED, 'advocate', 'sherzod.xolmatov@demo.lexlink.uz');
        $this->user(MockGovSeeder::ADMIN, 'admin', 'admin@demo.lexlink.uz');

        $this->profile($advocate, 'AL-00123', 'active', '"Rustamova va hamkorlar" advokatlik byurosi');
        $this->profile($revoked, 'AL-00087', 'revoked', '"Xolmatov" advokatlik byurosi');

        foreach ([$advocate, $revoked] as $u) {
            $this->wallet($u, 300_000);
        }

        $plan = Plan::where('code', 'advocate')->first();
        if ($plan && ! Subscription::where('user_id', $advocate->id)->where('period_end', '>=', now()->toDateString())->exists()) {
            Subscription::create([
                'user_id' => $advocate->id,
                'plan_id' => $plan->id,
                'period_start' => now()->startOfMonth()->toDateString(),
                'period_end' => now()->startOfMonth()->addMonth()->subDay()->toDateString(),
                'used_requests' => 0,
                'used_ai' => 0,
            ]);
        }

        AdvocateLawyerLink::updateOrCreate(
            ['advocate_id' => $advocate->id, 'lawyer_id' => $lawyer->id],
            [
                'invite_contact' => $lawyer->email,
                'token' => Str::random(64),
                'status' => 'accepted',
                'invited_at' => now()->subDays(14),
                'accepted_at' => now()->subDays(13),
            ],
        );

        $case = LegalCase::updateOrCreate(
            ['owner_id' => $advocate->id, 'title' => self::CASE_TITLE],
            [
                'court_case_number' => MockGovSeeder::DEMO_CASE,
                'client_name' => 'Karimova Nilufar Shavkatovna',
                'category' => 'alimony',
                'description' => "Voyaga yetmagan 2 nafar farzand ta'minoti uchun aliment undirish. Javobgar: Karimov Aziz Botirovich. "
                    ."Sud ajrimi asosida javobgarning daromadi to'g'risida ma'lumot olish kerak.",
                'parties' => [
                    ['role' => "Da'vogar", 'name' => 'Karimova N. Sh.'],
                    ['role' => 'Javobgar', 'name' => 'Karimov A. B.'],
                ],
                'status' => 'active',
            ],
        );

        CaseMember::updateOrCreate(
            ['case_id' => $case->id, 'user_id' => $advocate->id],
            ['role' => 'advocate', 'can_view_responses' => true, 'secrecy_declared_at' => now()],
        );
        CaseMember::updateOrCreate(
            ['case_id' => $case->id, 'user_id' => $lawyer->id],
            ['role' => 'lawyer', 'can_view_responses' => true, 'secrecy_declared_at' => null], // demo shows the declaration
        );
    }

    private function user(string $pinfl, string $role, string $email): User
    {
        $person = MockPerson::where('pinfl', $pinfl)->firstOrFail();

        $user = User::firstOrNew(['pinfl_hash' => Pinfl::hash($pinfl)]);
        $user->fill([
            'name' => $person->fullName(),
            'email' => $email,
            'password' => 'password',
            'oneid_sub' => MockPerson::oneIdSubFor($pinfl),
            'pinfl' => $pinfl,
            'phone' => $person->phone,
            'role' => $role,
        ]);
        $user->pinfl_hash = Pinfl::hash($pinfl); // explicit, in case model events are disabled
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    private function profile(User $user, string $license, string $status, string $bureau): void
    {
        AdvocateProfile::updateOrCreate(['user_id' => $user->id], [
            'license_number' => $license,
            'license_status' => $status,
            'bureau_name' => $bureau,
            'verified_at' => now()->subDays(30),
            'last_checked_at' => now(),
        ]);
    }

    private function wallet(User $user, int $amount): void
    {
        $wallet = Wallet::firstOrCreate(['user_id' => $user->id], ['balance' => 0]);

        if ($wallet->transactions()->exists()) {
            return;
        }

        $wallet->update(['balance' => $amount]);
        Transaction::create([
            'wallet_id' => $wallet->id,
            'type' => 'topup',
            'amount' => $amount,
            'balance_after' => $amount,
            'provider' => 'click',
            'status' => 'completed',
            'external_id' => 'DEMO-'.$user->id,
            'description' => "Demo balans to'ldirish",
        ]);
    }
}
