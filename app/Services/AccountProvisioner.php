<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Wallet;

/** Gives a freshly onboarded user an empty wallet and the free "start" plan. Idempotent. */
class AccountProvisioner
{
    public function provision(User $user): void
    {
        // TODO(billing): switch to BillingService::walletFor() once available.
        Wallet::firstOrCreate(['user_id' => $user->id], ['balance' => 0]);

        if ($user->subscriptions()->exists()) {
            return;
        }

        $plan = Plan::where('code', 'start')->first();

        if ($plan !== null) {
            Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'period_start' => now()->toDateString(),
                'period_end' => now()->addMonth()->toDateString(),
            ]);
        }
    }
}
