<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Http\Controllers\MockGov\OneIdController;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * "Demo rejimi" persona list for the login page. Built from the fictional mock OneID
 * population (mock_persons) so every persona can actually sign in through the mock IdP.
 * Never shown when lexlink.demo_mode is false.
 */
final class DemoPersonas
{
    private const GROUP_ORDER = ['advocate_active', 'lawyer', 'advocate_revoked', 'admin', 'new'];

    /** @return list<array{pinfl: string, name: string, label: string, hint: string, group: string}> */
    public static function all(int $perGroup = 2): array
    {
        if (! config('lexlink.demo_mode', true)) {
            return [];
        }

        try {
            if (! Schema::hasTable('mock_persons')) {
                return [];
            }

            $persons = DB::table('mock_persons')->orderBy('id')->limit(200)->get();
            // Curated demo people (defined by the mock OneID) go first.
            $preferred = defined('App\\Http\\Controllers\\MockGov\\OneIdController::DEMO_PINFLS')
                ? OneIdController::DEMO_PINFLS
                : [];
            $persons = $persons->sortBy(fn ($p) => ($i = array_search($p->pinfl, $preferred, true)) === false ? PHP_INT_MAX : $i)->values();
            $licenses = Schema::hasTable('mock_advocate_licenses')
                ? DB::table('mock_advocate_licenses')->pluck('status', 'pinfl')
                : collect();
        } catch (Throwable) {
            return [];
        }

        $users = User::with('advocateProfile')
            ->whereIn('pinfl_hash', $persons->map(fn ($p) => Pinfl::hash($p->pinfl))->all())
            ->get()->keyBy('pinfl_hash');

        $groups = array_fill_keys(self::GROUP_ORDER, []);

        foreach ($persons as $person) {
            $name = trim("{$person->last_name} {$person->first_name} {$person->middle_name}");
            $user = $users->get(Pinfl::hash($person->pinfl));
            $registryStatus = $licenses->get($person->pinfl);

            [$group, $label] = match (true) {
                $user?->role === UserRole::Admin => ['admin', 'Administrator'],
                $user?->role === UserRole::Lawyer => ['lawyer', 'Yurist'],
                $user?->role === UserRole::Advocate && $registryStatus === 'active' => ['advocate_active', 'Advokat (litsenziya faol)'],
                $user?->role === UserRole::Advocate => ['advocate_revoked', 'Advokat (litsenziya bekor qilingan)'],
                // Not registered yet: onboarding will check the registry.
                $user === null && $registryStatus === 'active' => ['advocate_active', 'Advokat (litsenziya faol), yangi foydalanuvchi'],
                $user === null && $registryStatus !== null => ['advocate_revoked', 'Advokat (litsenziya bekor qilingan), yangi foydalanuvchi'],
                $user === null => ['new', 'Yangi foydalanuvchi (litsenziyasiz)'],
                default => [null, null],
            };

            if ($group === null || count($groups[$group]) >= $perGroup) {
                continue;
            }

            $groups[$group][] = [
                'pinfl' => $person->pinfl,
                'name' => $name,
                'label' => $label,
                'hint' => Pinfl::mask($person->pinfl),
                'group' => $group,
            ];
        }

        return array_merge(...array_values($groups));
    }
}
