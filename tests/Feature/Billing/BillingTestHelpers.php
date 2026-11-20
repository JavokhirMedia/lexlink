<?php

namespace Tests\Feature\Billing;

use App\Enums\UserRole;
use App\Models\AdvocateProfile;
use App\Models\DataRequest;
use App\Models\LegalCase;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Wallet;

/** Local fixtures (plans are seeded by the mock-gov agent in real envs). */
trait BillingTestHelpers
{
    protected function seedPlans(): void
    {
        Plan::create(['code' => 'start', 'name' => 'Start', 'audience' => 'Yuristlar', 'price' => 0, 'included_requests' => 0, 'extra_price' => null, 'included_ai' => 0, 'features' => ['Loyiha tayyorlash']]);
        Plan::create(['code' => 'advocate', 'name' => 'Advokat', 'audience' => 'Yakka advokatlar', 'price' => 149_000, 'included_requests' => 10, 'extra_price' => 15_000, 'included_ai' => 5, 'features' => ['10 ta so\'rov']]);
        Plan::create(['code' => 'firm', 'name' => 'Firma', 'audience' => 'Advokatlik byurolari', 'price' => 490_000, 'included_requests' => 50, 'extra_price' => 10_000, 'included_ai' => 30, 'features' => ['50 ta so\'rov']]);
    }

    protected function advocate(string $plan = 'advocate', int $balance = 0, int $used = 0, int $usedAi = 0): User
    {
        $user = User::factory()->create(['role' => UserRole::Advocate]);
        AdvocateProfile::create(['user_id' => $user->id, 'license_number' => 'A-'.$user->id, 'license_status' => 'active']);
        Wallet::create(['user_id' => $user->id, 'balance' => $balance]);
        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => Plan::where('code', $plan)->value('id'),
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'used_requests' => $used,
            'used_ai' => $usedAi,
        ]);

        return $user;
    }

    protected function lawyer(): User
    {
        return User::factory()->create(['role' => UserRole::Lawyer]);
    }

    protected function dataRequest(User $owner, array $attrs = []): DataRequest
    {
        $case = LegalCase::create(['owner_id' => $owner->id, 'title' => 'Aliment undirish', 'client_name' => 'Mijoz A.']);

        return DataRequest::create([
            'case_id' => $case->id,
            'type' => 'salary',
            'subject_pinfl' => '31234567890123',
            'subject_name' => 'Test Shaxs',
            'status' => 'awaiting_signature',
            'created_by' => $owner->id,
            ...$attrs,
        ]);
    }
}
