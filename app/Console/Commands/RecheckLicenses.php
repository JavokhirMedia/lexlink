<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\LicenseChecker;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('lexlink:recheck-licenses')]
#[Description('Re-check every advocate licence against the advocate registry (revoked => signing rights removed)')]
class RecheckLicenses extends Command
{
    public function handle(LicenseChecker $checker): int
    {
        $active = $inactive = 0;

        User::where('role', UserRole::Advocate)->whereHas('advocateProfile')->with('advocateProfile')
            ->chunkById(100, function ($users) use ($checker, &$active, &$inactive) {
                foreach ($users as $user) {
                    $checker->refresh($user) ? $active++ : $inactive++;
                }
            });

        $this->info("Faol: {$active}, faol emas / tekshirib bo'lmadi: {$inactive}");

        return self::SUCCESS;
    }
}
