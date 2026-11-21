<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\CaseClientLink;
use App\Models\LegalCase;
use App\Models\Mock\MockAdvocateLicense;
use App\Models\Mock\MockAdvocateOrder;
use App\Models\Mock\MockPerson;
use App\Models\User;
use App\Support\Pinfl;
use Illuminate\Database\Seeder;

/**
 * Fictional "Advokatlar palatasi — elektron orderlar reyestri" + the client (ishonch bildiruvchi)
 * demo: Karimova Nilufar (client of AL-00123) and a ready client access link for the alimony case.
 * Requires MockGovSeeder + DemoUsersSeeder first.
 */
class AdvocateOrdersSeeder extends Seeder
{
    public const DEMO_ORDER = 'ORD-2026-000457';

    public const CANCELLED_ORDER = 'ORD-2026-000312';

    public const OTHER_ADVOCATE_ORDER = 'ORD-2026-000398';

    /** Demo client link: /client/access/{DEMO_TOKEN} */
    public const DEMO_TOKEN = 'demo-karimova-aliment-0457';

    public function run(): void
    {
        $name = fn (string $licence) => MockAdvocateLicense::where('license_number', $licence)->value('full_name') ?? $licence;
        $person = fn (string $pinfl) => MockPerson::where('pinfl', $pinfl)->first()?->fullName();

        $orders = [
            [self::DEMO_ORDER, 'AL-00123', MockGovSeeder::CLAIMANT, 'Karimova Nilufar Shavkatovna', 'Aliment undirish', '2026-11-05', 'active'],
            [self::CANCELLED_ORDER, 'AL-00123', '31105700262066', null, 'Mehnat nizosi — ishga tiklash', '2026-03-14', 'cancelled'],
            [self::OTHER_ADVOCATE_ORDER, 'AL-00045', '31312990262055', null, 'Mulk nizosi — ulushni ajratish', '2026-10-21', 'active'],
        ];

        foreach ($orders as [$number, $licence, $pinfl, $clientName, $matter, $issued, $status]) {
            MockAdvocateOrder::updateOrCreate(['order_number' => $number], [
                'license_number' => $licence,
                'advocate_name' => $name($licence),
                'client_pinfl' => $pinfl,
                'client_name' => $clientName ?? $person($pinfl) ?? 'Uydirma mijoz',
                'matter' => $matter,
                'issued_at' => $issued,
                'status' => $status,
            ]);
        }

        // Client user (role "client" — in production this role is obtained ONLY via a client link).
        $client = $this->clientUser(MockGovSeeder::CLAIMANT, 'nilufar.karimova@demo.lexlink.uz');

        $advocate = User::where('pinfl_hash', Pinfl::hash(MockGovSeeder::ADVOCATE))->first();
        $case = $advocate ? LegalCase::where('owner_id', $advocate->id)->where('title', DemoUsersSeeder::CASE_TITLE)->first() : null;

        if ($case && $client) {
            CaseClientLink::updateOrCreate(['token' => self::DEMO_TOKEN], [
                'case_id' => $case->id,
                'order_number' => self::DEMO_ORDER,
                'client_pinfl_hash' => Pinfl::hash(MockGovSeeder::CLAIMANT),
                'client_name' => 'Karimova Nilufar Shavkatovna',
                'created_by' => $advocate->id,
                'client_user_id' => $client->id,
                'granted_at' => now(),
                'revoked_at' => null,
            ]);
        }
    }

    private function clientUser(string $pinfl, string $email): ?User
    {
        $person = MockPerson::where('pinfl', $pinfl)->first();
        if (! $person) {
            return null;
        }

        $user = User::firstOrNew(['pinfl_hash' => Pinfl::hash($pinfl)]);
        $user->fill([
            'name' => $person->fullName(),
            'email' => $email,
            'password' => 'password',
            'oneid_sub' => MockPerson::oneIdSubFor($pinfl),
            'pinfl' => $pinfl,
            'phone' => $person->phone,
            'role' => $user->role ?? UserRole::Client,
        ]);
        $user->pinfl_hash = Pinfl::hash($pinfl);
        $user->email_verified_at ??= now();
        $user->save();

        return $user;
    }
}
