<?php

namespace Database\Seeders;

use App\Models\Mock\MockAdvocateLicense;
use App\Models\Mock\MockBankAccount;
use App\Models\Mock\MockBorderCrossing;
use App\Models\Mock\MockBusinessShare;
use App\Models\Mock\MockCourtCase;
use App\Models\Mock\MockCourtDecision;
use App\Models\Mock\MockPerson;
use App\Models\Mock\MockProperty;
use App\Models\Mock\MockSalary;
use App\Models\Mock\MockTaxProfile;
use App\Models\Mock\MockVehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Fictional government registries. EVERY person, number and organisation here is invented.
 * Re-runnable: registry tables are wiped and re-filled deterministically.
 * Demo identifiers are documented in docs/mock-gov-api.md.
 */
class MockGovSeeder extends Seeder
{
    // Demo scenario PINFLs (also used by DemoUsersSeeder)
    public const ADVOCATE = '41505880261034';     // Rustamova Dilnoza Akmalovna — AL-00123 active

    public const LAWYER = '32208950261047';       // Toshpulatov Jasur Baxtiyorovich

    public const REVOKED = '30311790261058';      // Xolmatov Sherzod Ravshanovich — AL-00087 revoked

    public const ADMIN = '42709920261069';        // Abdullayeva Malika Farhodovna

    public const DEBTOR = '31207870261012';       // Karimov Aziz Botirovich — alimony debtor

    public const CLAIMANT = '42303910261025';     // Karimova Nilufar Shavkatovna — client

    public const PROPERTY_SUBJECT = '31804850261076'; // Raximov Bekzod Ulug'bekovich

    public const DEMO_DECISION = '2-1001-2611/0457';

    /** Second decision in the demo case: bank secret + border crossings (court-only types). */
    public const DEMO_DECISION_2 = '2-1001-2611/0458';

    public const DEMO_CASE = '2-1001-2610/0398';

    /** Fictional banks only — never real bank names. */
    private const BANKS = ['"Namuna Bank" ATB', '"Sharq Kapital Bank" ATB', '"Yangi Asr Bank" ATB', '"Ipak Karvon Bank" AJ', '"Bunyodkor Moliya Bank" ATB'];

    private const CHECKPOINTS = [
        ['Toshkent xalqaro aeroporti', ['Turkiya', 'Rossiya', 'Birlashgan Arab Amirliklari', 'Janubiy Koreya', 'Germaniya']],
        ['Samarqand xalqaro aeroporti', ['Turkiya', 'Rossiya']],
        ['Oybek CHP', ["Qozog'iston"]],
        ['"Yallama" CHP', ["Qozog'iston"]],
        ["Do'stlik CHP", ["Qirg'iziston"]],
    ];

    private const COURTS = [
        'yunusobod' => 'Toshkent shahar Yunusobod tumanlararo fuqarolik ishlari sudi',
        'chilonzor' => 'Toshkent shahar Chilonzor tumanlararo fuqarolik ishlari sudi',
        'mirzo' => "Toshkent shahar Mirzo Ulug'bek tumanlararo fuqarolik ishlari sudi",
        'samarqand' => 'Samarqand viloyati Samarqand tumanlararo fuqarolik ishlari sudi',
        'zangiota' => 'Toshkent viloyati Zangiota tumanlararo fuqarolik ishlari sudi',
    ];

    private const EMPLOYERS = [
        ['"Namuna" MChJ', '301234567'],
        ['"Toshkent Qurilish Servis" MChJ', '302456781'],
        ['"Yangi Avlod Ta\'lim" NTM', '303567892'],
        ['"Ipak Yo\'li Logistika" MChJ', '304678913'],
        ['"Kod Soft" MChJ', '305789124'],
        ['"Samarqand Savdo" MChJ', '306891235'],
        ['"Shifo Med" xususiy klinikasi MChJ', '308123457'],
        ['Toshkent shahar 112-umumta\'lim maktabi', '200345678'],
        ['"Agro Invest Zangiota" MChJ', '309234568'],
        ['"Adolat Himoyasi" advokatlik firmasi', '307912346'],
    ];

    public function run(): void
    {
        mt_srand(20261120);

        foreach ([MockBorderCrossing::class, MockBankAccount::class, MockBusinessShare::class, MockTaxProfile::class, MockSalary::class, MockProperty::class, MockVehicle::class, MockCourtDecision::class, MockCourtCase::class, MockAdvocateLicense::class, MockPerson::class] as $model) {
            $model::query()->delete();
        }

        $persons = $this->persons();
        foreach ($persons as $p) {
            MockPerson::create($p);
        }
        $byPinfl = collect($persons)->keyBy('pinfl');
        $pinflOf = fn (string $last) => collect($persons)->firstWhere('last_name', $last)['pinfl'];
        $name = fn (string $pinfl) => trim(implode(' ', [$byPinfl[$pinfl]['last_name'], $byPinfl[$pinfl]['first_name'], $byPinfl[$pinfl]['middle_name']]));

        $this->licenses($pinflOf, $name);
        $this->courts($pinflOf, $name);
        $this->salaries($persons);
        $this->properties($persons, $pinflOf);
        $this->vehicles($persons, $pinflOf);
        $this->businessShares($persons, $pinflOf);
        $this->taxProfiles($persons);
        $this->bankAccounts($persons, $pinflOf);
        $this->borderCrossings($persons, $pinflOf);
    }

    /** 30 fictional citizens. */
    private function persons(): array
    {
        $fixed = [
            [self::ADVOCATE, 'Rustamova', 'Dilnoza', 'Akmalovna', '1988-05-15', '+998901234501'],
            [self::LAWYER, 'Toshpulatov', 'Jasur', 'Baxtiyorovich', '1995-08-22', '+998901234502'],
            [self::REVOKED, 'Xolmatov', 'Sherzod', 'Ravshanovich', '1979-11-03', '+998901234503'],
            [self::ADMIN, 'Abdullayeva', 'Malika', 'Farhodovna', '1992-09-27', '+998901234504'],
            [self::DEBTOR, 'Karimov', 'Aziz', 'Botirovich', '1987-07-12', '+998901234505'],
            [self::CLAIMANT, 'Karimova', 'Nilufar', 'Shavkatovna', '1991-03-23', '+998901234506'],
            [self::PROPERTY_SUBJECT, 'Raximov', 'Bekzod', "Ulug'bekovich", '1985-04-18', '+998901234507'],
        ];

        $others = [
            ['Yusupov', 'Otabek', 'Rustamovich', 'm'], ['Nazarov', 'Javohir', 'Alisherovich', 'm'],
            ['Saidov', 'Farrux', 'Ilhomovich', 'm'], ['Ergashev', 'Sardor', 'Komilovich', 'm'],
            ['Mirzayev', "Ulug'bek", 'Erkinovich', 'm'], ['Qodirov', 'Doniyor', 'Shuhratovich', 'm'],
            ['Hasanov', 'Bobur', 'Nodirovich', 'm'], ['Tursunov', 'Akmal', 'Zafarovich', 'm'],
            ['Ismoilov', 'Rustam', 'Hamidovich', 'm'], ['Sobirov', 'Jamshid', 'Olimovich', 'm'],
            ['Aliyev', 'Shoxrux', 'Abdullayevich', 'm'], ['Umarov', 'Elyor', 'Bahodirovich', 'm'],
            ["Jo'rayev", 'Islom', 'Sanjarovich', 'm'], ['Abdurahmonov', 'Timur', 'Murodovich', 'm'],
            ['Ahmedova', 'Gulnora', 'Baxromovna', 'f'], ['Rahimova', 'Sevara', 'Anvarovna', 'f'],
            ['Usmonova', 'Zarina', 'Jahongirovna', 'f'], ['Mahmudova', 'Nodira', 'Ikromovna', 'f'],
            ['Xasanova', 'Madina', 'Sherzodovna', 'f'], ['Normatova', 'Feruza', 'Tohirovna', 'f'],
            ['Sultonova', 'Kamola', 'Rustamovna', 'f'], ['Ibragimova', 'Lola', 'Murodovna', 'f'],
            ["Yo'ldosheva", 'Shahnoza', 'Azamatovna', 'f'],
        ];

        $streets = ['Amir Temur', 'Mustaqillik', 'Bunyodkor', 'Shota Rustaveli', 'Navoiy', 'Bobur', 'Farg\'ona yo\'li', 'Qatortol'];
        $districts = ['Yunusobod', 'Chilonzor', "Mirzo Ulug'bek", 'Yakkasaroy', 'Shayxontohur', 'Sergeli', 'Olmazor'];

        $rows = [];
        foreach ($fixed as $i => [$pinfl, $last, $first, $middle, $birth, $phone]) {
            $rows[] = $this->person($pinfl, $last, $first, $middle, $birth, $phone, $districts[$i % 7], $streets[$i % 8], $i);
        }

        foreach ($others as $i => [$last, $first, $middle, $g]) {
            $birth = Carbon::create(mt_rand(1965, 2001), mt_rand(1, 12), mt_rand(1, 28));
            $century = $birth->year >= 2000 ? ($g === 'm' ? 5 : 6) : ($g === 'm' ? 3 : 4);
            $serial = 200 + $i;
            $pinfl = $century.$birth->format('dmy').'026'.$serial.($serial % 10);
            $rows[] = $this->person($pinfl, $last, $first, $middle, $birth->toDateString(), sprintf('+99893%07d', 1000000 + $i * 7919), $districts[$i % 7], $streets[$i % 8], $i + 7);
        }

        return $rows;
    }

    private function person(string $pinfl, string $last, string $first, string $middle, string $birth, string $phone, string $district, string $street, int $i): array
    {
        return [
            'pinfl' => $pinfl,
            'last_name' => $last,
            'first_name' => $first,
            'middle_name' => $middle,
            'birth_date' => $birth,
            'passport' => sprintf('A%s%07d', chr(65 + ($i % 26)), 1234567 + $i * 1031),
            'address' => "Toshkent shahri, {$district} tumani, {$street} ko'chasi, ".(10 + $i).'-uy',
            'phone' => $phone,
        ];
    }

    /** 5 advocate licences, 1 revoked. */
    private function licenses(callable $pinflOf, callable $name): void
    {
        $rows = [
            ['AL-00123', self::ADVOCATE, 'active', '"Rustamova va hamkorlar" advokatlik byurosi', '2014-06-10', null],
            ['AL-00087', self::REVOKED, 'revoked', '"Xolmatov" advokatlik byurosi', '2009-02-17', '2026-10-01'],
            ['AL-00045', $pinflOf('Yusupov'), 'active', '"Adolat Himoyasi" advokatlik firmasi', '2006-09-01', null],
            ['AL-00156', $pinflOf('Nazarov'), 'active', 'Yunusobod tumani 3-sonli advokatlik byurosi', '2017-03-22', null],
            ['AL-00201', $pinflOf('Saidov'), 'active', '"Samarqand Huquq" advokatlik firmasi', '2020-11-05', null],
        ];

        foreach ($rows as [$number, $pinfl, $status, $bureau, $issued, $revoked]) {
            MockAdvocateLicense::create([
                'license_number' => $number,
                'pinfl' => $pinfl,
                'full_name' => $name($pinfl),
                'status' => $status,
                'bureau_name' => $bureau,
                'issued_at' => $issued,
                'revoked_at' => $revoked,
            ]);
        }
    }

    /** 15 court cases + 10 decisions (2 expired). */
    private function courts(callable $pinflOf, callable $name): void
    {
        $c = self::COURTS;
        $party = fn (string $role, string $pinfl) => ['role' => $role, 'name' => $name($pinfl), 'pinfl' => $pinfl];
        $org = fn (string $role, string $org) => ['role' => $role, 'name' => $org, 'pinfl' => null];

        // [case_number, court, judge, category, status, parties, hearings, decision?]
        // decision: [number, type, subject_pinfl, allowed_types, issued, expires, summary, requester_license]
        $cases = [
            [self::DEMO_CASE, $c['yunusobod'], 'Ergasheva M. T.', 'Aliment undirish', 'Ish yuritishda',
                [$party("Da'vogar", self::CLAIMANT), $party('Javobgar', self::DEBTOR)],
                [['date' => '2026-11-05', 'time' => '10:00', 'room' => '12-xona', 'note' => 'Tayyorgarlik bosqichi'],
                    ['date' => '2026-12-03', 'time' => '14:30', 'room' => '12-xona', 'note' => 'Asosiy sud majlisi']],
                [
                    [self::DEMO_DECISION, 'ajrim', self::DEBTOR, ['salary', 'court_case'], '2026-11-10', '2027-05-10',
                        "Javobgarning ish haqi va boshqa daromadlari to'g'risidagi ma'lumotlarni soliq va pensiya organlaridan talab qilib olish to'g'risida ajrim.", null],
                    [self::DEMO_DECISION_2, 'ajrim', self::DEBTOR, ['bank_accounts', 'border_crossing'], '2026-11-10', '2027-05-10',
                        "Javobgarning banklardagi hisobvaraqlari (qoldiq va aylanmalar) hamda chegaradan o'tish ma'lumotlarini aliment majburiyatidan bo'yin tovlash holatini aniqlash uchun talab qilib olish to'g'risida ajrim.", null],
                ]],
            ['2-1004-2608/0655', $c['chilonzor'], 'Qosimov B. A.', 'Er-xotin umumiy mol-mulkini taqsimlash', "Ko'rib chiqilmoqda",
                [$party("Da'vogar", $pinflOf('Rahimova')), $party('Javobgar', self::PROPERTY_SUBJECT)],
                [['date' => '2026-10-14', 'time' => '11:00', 'room' => '5-xona', 'note' => 'Sud majlisi'],
                    ['date' => '2026-12-09', 'time' => '11:00', 'room' => '5-xona', 'note' => 'Ekspertiza natijalarini ko\'rish']],
                ['2-1004-2609/0812', 'ajrim', self::PROPERTY_SUBJECT, ['property', 'vehicle'], '2026-09-15', '2027-03-15',
                    "Javobgar nomidagi ko'chmas mulk va avtotransport vositalari to'g'risidagi ma'lumotlarni talab qilish to'g'risida ajrim.", null]],
            ['2-1001-2501/0047', $c['yunusobod'], 'Ergasheva M. T.', 'Aliment undirish', 'Hal qilingan',
                [$party("Da'vogar", $pinflOf('Ahmedova')), $party('Javobgar', $pinflOf('Hasanov'))],
                [['date' => '2025-03-11', 'time' => '09:30', 'room' => '12-xona', 'note' => 'Sud majlisi']],
                ['2-1001-2502/0133', 'ajrim', $pinflOf('Hasanov'), ['salary'], '2025-02-14', '2025-08-14',
                    "Javobgarning daromadi to'g'risidagi ma'lumotlarni talab qilish to'g'risida ajrim (muddati o'tgan).", null]],
            ['2-1103-2503/0190', $c['zangiota'], 'Abdullayev S. X.', 'Qarzni undirish', 'Hal qilingan',
                [$party("Da'vogar", $pinflOf('Tursunov')), $party('Javobgar', $pinflOf('Ismoilov'))],
                [['date' => '2025-05-06', 'time' => '15:00', 'room' => '3-xona', 'note' => 'Sud majlisi']],
                ['2-1103-2504/0271', 'ajrim', $pinflOf('Ismoilov'), ['property', 'vehicle'], '2025-04-20', '2026-01-20',
                    "Javobgarning mol-mulki to'g'risidagi ma'lumotlarni talab qilish to'g'risida ajrim (muddati o'tgan).", null]],
            ['2-1007-2607/0231', $c['mirzo'], 'Rahmonov D. S.', 'Aliment undirish', 'Ish yuritishda',
                [$party("Da'vogar", $pinflOf('Usmonova')), $party('Javobgar', $pinflOf('Sobirov'))],
                [['date' => '2026-08-20', 'time' => '10:30', 'room' => '7-xona', 'note' => 'Tayyorgarlik bosqichi'],
                    ['date' => '2026-11-26', 'time' => '10:30', 'room' => '7-xona', 'note' => 'Asosiy sud majlisi']],
                ['2-1007-2608/0302', 'ajrim', $pinflOf('Sobirov'), ['salary', 'property', 'vehicle', 'court_case', 'bank_accounts'], '2026-08-28', '2027-02-28',
                    "Javobgarning daromadlari va mol-mulki to'g'risidagi ma'lumotlarni talab qilish to'g'risida ajrim.", null]],
            ['2-1801-2609/0488', $c['samarqand'], 'Tojiyeva N. R.', 'Merosni taqsimlash', 'Ish yuritishda',
                [$party("Da'vogar", $pinflOf('Sultonova')), $party('Javobgar', $pinflOf('Mirzayev'))],
                [['date' => '2026-10-02', 'time' => '14:00', 'room' => '2-xona', 'note' => 'Sud majlisi']],
                ['2-1801-2610/0544', 'ajrim', $pinflOf('Mirzayev'), ['property'], '2026-10-01', '2027-04-01',
                    "Meros tarkibidagi ko'chmas mulk to'g'risidagi ma'lumotlarni talab qilish to'g'risida ajrim.", null]],
            ['2-1004-2606/0560', $c['chilonzor'], 'Qosimov B. A.', 'YTH natijasida yetkazilgan zararni qoplash', 'Hal qilingan',
                [$party("Da'vogar", $pinflOf('Aliyev')), $party('Javobgar', $pinflOf('Umarov'))],
                [['date' => '2026-07-15', 'time' => '16:00', 'room' => '5-xona', 'note' => 'Sud majlisi']],
                ['2-1004-2610/0911', 'hal qiluv qarori', $pinflOf('Umarov'), ['vehicle', 'court_case'], '2026-10-31', '2027-01-31',
                    "Zararni undirish va ijro uchun javobgar nomidagi transport vositalari to'g'risida ma'lumot olish.", null]],
            ['2-1001-2608/0320', $c['yunusobod'], 'Ergasheva M. T.', 'Aliment undirish', 'Ish yuritishda',
                [$party("Da'vogar", $pinflOf('Mahmudova')), $party('Javobgar', $pinflOf('Qodirov'))],
                [['date' => '2026-09-18', 'time' => '09:00', 'room' => '12-xona', 'note' => 'Sud majlisi']],
                ['2-1001-2609/0377', 'ajrim', $pinflOf('Qodirov'), ['salary'], '2026-09-20', '2027-03-20',
                    "Javobgarning ish haqi to'g'risidagi ma'lumotlarni da'vogar advokati orqali talab qilish to'g'risida ajrim.", 'AL-00045']],
            ['2-1103-2609/0366', $c['zangiota'], 'Abdullayev S. X.', 'Qarzni undirish', "Ko'rib chiqilmoqda",
                [$party("Da'vogar", $pinflOf('Ergashev')), $party('Javobgar', $pinflOf("Jo'rayev"))],
                [['date' => '2026-10-21', 'time' => '11:30', 'room' => '3-xona', 'note' => 'Sud majlisi']],
                ['2-1103-2610/0418', 'ajrim', $pinflOf("Jo'rayev"), ['salary', 'property'], '2026-10-25', '2027-06-30',
                    "Javobgarning daromadi va mol-mulki to'g'risidagi ma'lumotlarni talab qilish to'g'risida ajrim.", null]],
            ['2-1007-2610/0297', $c['mirzo'], 'Rahmonov D. S.', 'Nikohdan ajratish va aliment undirish', 'Ish yuritishda',
                [$party("Da'vogar", $pinflOf("Yo'ldosheva")), $party('Javobgar', $pinflOf('Abdurahmonov'))],
                [['date' => '2026-11-12', 'time' => '10:00', 'room' => '7-xona', 'note' => 'Yarashtirish muddati belgilandi']],
                ['2-1007-2611/0350', 'ajrim', $pinflOf('Abdurahmonov'), ['salary', 'court_case'], '2026-11-12', '2027-05-31',
                    "Javobgarning daromadlari to'g'risidagi ma'lumotlarni talab qilish to'g'risida ajrim.", null]],
            ['2-1001-2605/0215', $c['yunusobod'], 'Ergasheva M. T.', 'Mehnat nizosi (ish haqini undirish)', 'Hal qilingan',
                [$party("Da'vogar", $pinflOf('Ergashev')), $org('Javobgar', '"Namuna" MChJ')],
                [['date' => '2026-06-10', 'time' => '15:30', 'room' => '12-xona', 'note' => 'Sud majlisi']], null],
            ['2-1004-2611/0973', $c['chilonzor'], 'Qosimov B. A.', 'Uy-joydan foydalanish nizosi', "Ko'rib chiqilmoqda",
                [$party("Da'vogar", $pinflOf('Normatova')), $party('Javobgar', $pinflOf('Aliyev'))],
                [['date' => '2026-12-01', 'time' => '09:30', 'room' => '5-xona', 'note' => 'Tayyorgarlik bosqichi']], null],
            ['2-1801-2604/0302', $c['samarqand'], 'Tojiyeva N. R.', "Iste'molchi huquqlarini himoya qilish", 'Hal qilingan',
                [$party("Da'vogar", $pinflOf('Sultonova')), $org('Javobgar', '"Samarqand Savdo" MChJ')],
                [['date' => '2026-05-19', 'time' => '11:00', 'room' => '2-xona', 'note' => 'Sud majlisi']], null],
            ['2-1007-2609/0255', $c['mirzo'], 'Rahmonov D. S.', 'Shartnoma nizosi', 'Ish yuritishda',
                [$party("Da'vogar", $pinflOf('Ibragimova')), $party('Javobgar', $pinflOf('Xasanova'))],
                [['date' => '2026-11-27', 'time' => '14:00', 'room' => '7-xona', 'note' => 'Sud majlisi']], null],
            ['2-1103-2611/0471', $c['zangiota'], 'Abdullayev S. X.', "Aliment miqdorini o'zgartirish", 'Ish yuritishda',
                [$party("Da'vogar", $pinflOf("Yo'ldosheva")), $party('Javobgar', $pinflOf('Tursunov'))],
                [['date' => '2026-12-10', 'time' => '10:00', 'room' => '3-xona', 'note' => 'Sud majlisi']], null],
        ];

        foreach ($cases as [$number, $court, $judge, $category, $status, $parties, $hearings, $decision]) {
            $decisions = [];

            foreach ($decision === null ? [] : (is_array($decision[0]) ? $decision : [$decision]) as $one) {
                [$dNumber, $dType, $subject, $allowed, $issued, $expires, $summary, $license] = $one;
                MockCourtDecision::create([
                    'number' => $dNumber,
                    'case_number' => $number,
                    'court' => $court,
                    'judge' => $judge,
                    'decision_type' => $dType,
                    'subject_pinfl' => $subject,
                    'subject_name' => $name($subject),
                    'allowed_types' => $allowed,
                    'requester_license' => $license,
                    'issued_at' => $issued,
                    'expires_at' => $expires,
                    'summary' => $summary,
                ]);
                $decisions[] = ['number' => $dNumber, 'date' => $issued, 'type' => $dType, 'summary' => $summary];
            }

            if ($status === 'Hal qilingan') {
                $last = end($hearings)['date'];
                $decisions[] = ['number' => $number, 'date' => $last, 'type' => 'hal qiluv qarori', 'summary' => "Da'vo qisman qanoatlantirildi."];
            }

            MockCourtCase::create([
                'case_number' => $number,
                'court' => $court,
                'judge' => $judge,
                'category' => $category,
                'status' => $status,
                'parties' => $parties,
                'hearings' => $hearings,
                'decisions' => $decisions,
            ]);
        }
    }

    /** 12 months (2025-11..2026-10) for everyone; ~30% have a second employer. */
    private function salaries(array $persons): void
    {
        $months = collect(range(0, 11))->map(fn (int $i) => Carbon::create(2025, 11, 1)->addMonths($i)->format('Y-m'))->all();
        $rows = [];

        // Demo debtor: 12 months at "Namuna" + 6 months part-time at "Toshkent Qurilish Servis"
        foreach ($months as $i => $period) {
            $rows[] = $this->salaryRow(self::DEBTOR, self::EMPLOYERS[0], $period, $period === '2026-03' ? 7_200_000 : 6_500_000);
            if ($i >= 6) {
                $rows[] = $this->salaryRow(self::DEBTOR, self::EMPLOYERS[1], $period, 2_800_000);
            }
        }

        foreach ($persons as $idx => $p) {
            if ($p['pinfl'] === self::DEBTOR) {
                continue;
            }
            $main = self::EMPLOYERS[$p['pinfl'] === self::ADVOCATE || $p['pinfl'] === self::REVOKED ? 9 : mt_rand(0, 8)];
            $base = mt_rand(50, 300) * 50_000; // 2.5 .. 15 mln
            $second = mt_rand(1, 100) <= 30 ? self::EMPLOYERS[($idx + 3) % 9] : null;
            $secondFrom = mt_rand(0, 8);
            $secondBase = mt_rand(30, 80) * 50_000;

            foreach ($months as $i => $period) {
                $amount = $base + ($period === '2025-12' ? mt_rand(0, 4) * 250_000 : 0);
                $rows[] = $this->salaryRow($p['pinfl'], $main, $period, $amount);
                if ($second && $second !== $main && $i >= $secondFrom) {
                    $rows[] = $this->salaryRow($p['pinfl'], $second, $period, $secondBase);
                }
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            MockSalary::insert($chunk);
        }
    }

    private function salaryRow(string $pinfl, array $employer, string $period, int $amount): array
    {
        return [
            'pinfl' => $pinfl,
            'employer_name' => $employer[0],
            'employer_tin' => $employer[1],
            'period' => $period,
            'amount' => $amount,
            'pension_contribution' => (int) round($amount * 0.001), // INPS 0.1%
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function properties(array $persons, callable $pinflOf): void
    {
        $fixed = [
            [self::DEBTOR, '10:01:05:01:02:0012', 'Kvartira', 'Toshkent shahri, Yunusobod tumani, 11-mavze, 23-uy, 45-xonadon', 68.40, '1/1', '2019-03-12', []],
            [self::PROPERTY_SUBJECT, '10:04:02:03:01:0457', 'Kvartira', 'Toshkent shahri, Chilonzor tumani, 9-kvartal, 14-uy, 31-xonadon', 74.20, '1/2', '2016-08-30',
                [['type' => 'Ipoteka', 'holder' => '"Namuna Bank" ATB', 'since' => '2016-08-30']]],
            [self::PROPERTY_SUBJECT, '11:09:01:04:05:0233', 'Yer uchastkasi', 'Toshkent viloyati, Zangiota tumani, Eshonguzar MFY, 7-uchastka', 600.00, '1/1', '2021-05-17', []],
            [$pinflOf('Sobirov'), '10:07:03:02:04:0118', 'Uy-joy', "Toshkent shahri, Mirzo Ulug'bek tumani, Qorasuv-3 dahasi, 5-uy", 142.50, '1/1', '2012-11-02',
                [['type' => 'Sud tomonidan xatlov', 'holder' => self::COURTS['mirzo'], 'since' => '2026-09-01']]],
            [$pinflOf('Mirzayev'), '14:10:02:01:03:0077', 'Uy-joy', "Samarqand shahri, Bog'ishamol ko'chasi, 21-uy", 210.00, '1/3', '2008-04-25', []],
            [$pinflOf("Jo'rayev"), '10:12:01:05:02:0390', 'Noturar bino', "Toshkent shahri, Sergeli tumani, Yangi Sergeli ko'chasi, 3-bino", 95.00, '1/1', '2022-02-14', []],
            [$pinflOf('Ismoilov'), '11:09:03:02:01:0145', 'Kvartira', "Toshkent viloyati, Zangiota tumani, Keles ko'chasi, 8-uy, 12-xonadon", 56.70, '1/1', '2018-07-09', []],
        ];

        foreach ($fixed as $row) {
            $this->property(...$row);
        }

        $types = ['Kvartira', 'Kvartira', 'Uy-joy', 'Yer uchastkasi'];
        $taken = array_column($fixed, 0);
        foreach ($persons as $i => $p) {
            if (in_array($p['pinfl'], $taken, true) || mt_rand(1, 100) > 55) {
                continue;
            }
            $type = $types[mt_rand(0, 3)];
            $area = match ($type) {
                'Kvartira' => mt_rand(380, 1100) / 10, 'Uy-joy' => mt_rand(900, 2600) / 10, default => (float) mt_rand(4, 12) * 100
            };
            $this->property(
                $p['pinfl'],
                sprintf('10:%02d:%02d:%02d:%02d:%04d', mt_rand(1, 12), mt_rand(1, 9), mt_rand(1, 6), mt_rand(1, 5), mt_rand(10, 999)),
                $type,
                $p['address'].($type === 'Kvartira' ? ', '.mt_rand(1, 80).'-xonadon' : ''),
                $area,
                mt_rand(1, 4) === 1 ? '1/2' : '1/1',
                Carbon::create(mt_rand(2005, 2025), mt_rand(1, 12), mt_rand(1, 28))->toDateString(),
                [],
            );
        }
    }

    private function property(string $pinfl, string $cadastral, string $type, string $address, float $area, string $share, string $registered, array $enc): void
    {
        MockProperty::create([
            'pinfl' => $pinfl, 'cadastral_number' => $cadastral, 'type' => $type, 'address' => $address,
            'area' => $area, 'share' => $share, 'registered_at' => $registered, 'encumbrances' => $enc,
        ]);
    }

    private function vehicles(array $persons, callable $pinflOf): void
    {
        $fixed = [
            [self::DEBTOR, 'Chevrolet Cobalt', 2021, '01 A 123 BC', 'Oq', 'XWBJA69V9MA012345', '2021-06-04', null],
            [self::PROPERTY_SUBJECT, 'Chevrolet Malibu 2', 2022, '01 B 777 KA', 'Qora', 'XWBTF69E2NA104821', '2022-03-11',
                ['holder' => '"Namuna Bank" ATB', 'since' => '2022-03-11']],
            [$pinflOf('Sobirov'), 'Kia K5', 2023, '01 S 404 TA', 'Kulrang', 'XWEK5S41BPA007731', '2023-09-19', null],
            [$pinflOf('Umarov'), 'Chevrolet Tracker', 2024, '10 U 315 MA', "Ko'k", 'XWBTJ76D8RA221490', '2024-01-25', null],
            [$pinflOf('Ismoilov'), 'Chevrolet Damas', 2017, '10 D 902 AA', 'Oq', 'XWB4A11BDHA331902', '2017-05-03', null],
        ];

        foreach ($fixed as $row) {
            $this->vehicle(...$row);
        }

        $models = ['Chevrolet Nexia 3', 'Chevrolet Spark', 'Chevrolet Gentra', 'Chevrolet Onix', 'BYD Song Plus', 'Hyundai Sonata', 'Chevrolet Lacetti'];
        $colors = ['Oq', 'Qora', 'Kumushrang', 'Kulrang', "Ko'k", 'Qizil'];
        $letters = 'ABCDEHKLMNOPSTUXYZ';
        $taken = array_column($fixed, 0);

        foreach ($persons as $p) {
            if (in_array($p['pinfl'], $taken, true) || mt_rand(1, 100) > 45) {
                continue;
            }
            $year = mt_rand(2012, 2025);
            $plate = sprintf('%s %s %03d %s%s', ['01', '10', '30'][mt_rand(0, 2)], $letters[mt_rand(0, 17)], mt_rand(100, 999), $letters[mt_rand(0, 17)], $letters[mt_rand(0, 17)]);
            $this->vehicle($p['pinfl'], $models[mt_rand(0, 6)], $year, $plate, $colors[mt_rand(0, 5)], $this->vin(), Carbon::create($year, mt_rand(1, 12), mt_rand(1, 28))->toDateString(), null);
        }
    }

    private function vehicle(string $pinfl, string $model, int $year, string $plate, string $color, string $vin, string $registered, ?array $pledge): void
    {
        MockVehicle::create([
            'pinfl' => $pinfl, 'model' => $model, 'year' => $year, 'plate' => $plate, 'color' => $color,
            'vin' => $vin, 'registered_at' => $registered, 'pledge' => $pledge,
        ]);
    }

    /** Participations in legal entities (Soliq kodeksi 29-m. 6-band — not a tax secret). */
    private function businessShares(array $persons, callable $pinflOf): void
    {
        $fixed = [
            [self::DEBTOR, '"Karimov Savdo Servis" MChJ', '310457812', 40.00, 50_000_000, '2022-04-18', 'Faol'],
            [self::PROPERTY_SUBJECT, '"Bekzod Qurilish Montaj" MChJ', '311238904', 100.00, 120_000_000, '2019-09-02', 'Faol'],
            [self::PROPERTY_SUBJECT, '"Chilonzor Avto Servis" MChJ', '312904417', 25.00, 40_000_000, '2021-02-11', "Faoliyati to'xtatilgan"],
            [$pinflOf('Sobirov'), '"Qorasuv Agro" fermer xo\'jaligi', '313550271', 50.00, 30_000_000, '2018-06-25', 'Faol'],
            [$pinflOf("Jo'rayev"), '"Sergeli Plast" MChJ', '314671239', 33.33, 90_000_000, '2023-03-14', 'Faol'],
            [$pinflOf('Mirzayev'), '"Samarqand Meva Eksport" MChJ', '315782340', 15.00, 200_000_000, '2017-11-30', 'Tugatish jarayonida'],
        ];
        $rows = [];
        foreach ($fixed as [$pinfl, $name, $tin, $pct, $capital, $since, $status]) {
            $rows[] = $this->shareRow($pinfl, $name, $tin, $pct, $capital, $since, $status);
        }

        $names = ['"Navro\'z Print" MChJ', '"Yashil Vodiy Agro" MChJ', '"Zamin IT Yechimlar" MChJ', '"Oq Saroy Mebel" MChJ', '"Sharq Tekstil Invest" MChJ', '"Toshkent Non Savdo" XK'];
        $taken = array_column($fixed, 0);
        foreach ($persons as $i => $p) {
            if (in_array($p['pinfl'], $taken, true) || mt_rand(1, 100) > 25) {
                continue;
            }
            $rows[] = $this->shareRow(
                $p['pinfl'], $names[$i % count($names)], (string) (316000000 + $i * 7307),
                [10.0, 20.0, 25.0, 50.0, 100.0][mt_rand(0, 4)], mt_rand(2, 40) * 5_000_000,
                Carbon::create(mt_rand(2012, 2025), mt_rand(1, 12), mt_rand(1, 28))->toDateString(), 'Faol',
            );
        }

        MockBusinessShare::insert($rows);
    }

    private function shareRow(string $pinfl, string $name, string $tin, float $pct, int $capital, string $since, string $status): array
    {
        return [
            'pinfl' => $pinfl, 'company_name' => $name, 'company_tin' => $tin, 'share_percent' => $pct,
            'charter_capital' => $capital, 'since' => $since, 'company_status' => $status,
            'created_at' => now(), 'updated_at' => now(),
        ];
    }

    /** Open part of tax data (Soliq kodeksi 29-m. 1–8-bandlar) for everyone. */
    private function taxProfiles(array $persons): void
    {
        // last 12 months of seeded salary (2025-11..2026-10) as the income base
        $yearIncome = MockSalary::query()->selectRaw('pinfl, SUM(amount) as total')->groupBy('pinfl')->pluck('total', 'pinfl');
        $activities = ['Chakana savdo (oziq-ovqat)', "Go'zallik saloni xizmatlari", 'Yuk tashish xizmatlari', 'Kompyuter ta\'mirlash', 'Tikuvchilik'];
        $rows = [];

        foreach ($persons as $i => $p) {
            $isDebtor = $p['pinfl'] === self::DEBTOR;
            $ie = ! $isDebtor && mt_rand(1, 100) <= 18;
            $income = (int) ($yearIncome[$p['pinfl']] ?? 0);
            $rows[] = [
                'pinfl' => $p['pinfl'],
                'tin' => $isDebtor ? '487120345' : (string) (480000000 + $i * 104729 % 9999999),
                'tax_regime' => $ie
                    ? (mt_rand(0, 1) ? 'Aylanmadan olinadigan soliq (YaTT)' : "Qat'iy belgilangan soliq (YaTT)")
                    : "Jismoniy shaxslardan olinadigan daromad solig'i (ish beruvchi orqali)",
                'vat_payer' => $ie && mt_rand(1, 100) <= 15,
                'is_individual_entrepreneur' => $ie,
                'ie_activity' => $ie ? $activities[$i % count($activities)] : null,
                'tax_debt' => $isDebtor ? 1_200_000 : (mt_rand(1, 100) <= 12 ? mt_rand(1, 40) * 50_000 : 0),
                'taxes_paid_last_year' => (int) round($income * 0.12 / 1000) * 1000 + ($ie ? mt_rand(5, 30) * 100_000 : 0),
                'registered_at' => Carbon::parse($p['birth_date'])->addYears(18)->addDays(mt_rand(0, 300))->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        MockTaxProfile::insert($rows);
    }

    /** Bank accounts (bank secret). Turnover = last 6 months. */
    private function bankAccounts(array $persons, callable $pinflOf): void
    {
        $fixed = [
            [self::DEBTOR, self::BANKS[0], '20206000****4417', 'UZS', 18_436_500, 41_250_000, 39_870_000, '2019-02-14', []],
            [self::DEBTOR, self::BANKS[1], '20206840****0932', 'USD', 2_350, 6_800, 5_100, '2024-07-01', []],
            [self::PROPERTY_SUBJECT, self::BANKS[0], '20206000****7781', 'UZS', 4_120_000, 38_400_000, 37_950_000, '2016-08-30',
                [['type' => 'Ipoteka bo\'yicha avtomatik yechish', 'by' => self::BANKS[0], 'since' => '2016-08-30']]],
            [$pinflOf('Sobirov'), self::BANKS[2], '20206000****3150', 'UZS', 960_000, 22_100_000, 23_400_000, '2020-05-12',
                [['type' => "Ijro hujjati bo'yicha hisobvaraqqa xatlov", 'by' => "Majburiy ijro byurosi Mirzo Ulug'bek tuman bo'limi", 'since' => '2026-09-05']]],
        ];
        $rows = [];
        foreach ($fixed as $row) {
            $rows[] = $this->accountRow(...$row);
        }

        $taken = array_column($fixed, 0);
        foreach ($persons as $i => $p) {
            if (in_array($p['pinfl'], $taken, true)) {
                continue;
            }
            $count = mt_rand(1, 100) <= 30 ? 2 : 1;
            for ($k = 0; $k < $count; $k++) {
                $usd = $k === 1 && mt_rand(0, 1);
                $in = $usd ? mt_rand(5, 60) * 100 : mt_rand(10, 120) * 500_000;
                $rows[] = $this->accountRow(
                    $p['pinfl'], self::BANKS[($i + $k) % count(self::BANKS)],
                    sprintf('20206%s****%04d', $usd ? '840' : '000', ($i * 37 + $k * 911) % 10000),
                    $usd ? 'USD' : 'UZS',
                    $usd ? mt_rand(1, 80) * 50 : mt_rand(1, 60) * 250_000,
                    $in, (int) round($in * mt_rand(80, 105) / 100),
                    Carbon::create(mt_rand(2012, 2025), mt_rand(1, 12), mt_rand(1, 28))->toDateString(), [],
                );
            }
        }

        MockBankAccount::insert($rows);
    }

    private function accountRow(string $pinfl, string $bank, string $mask, string $currency, int $balance, int $in, int $out, string $opened, array $restrictions): array
    {
        return [
            'pinfl' => $pinfl, 'bank_name' => $bank, 'account_mask' => $mask, 'currency' => $currency, 'balance' => $balance,
            'turnover_in' => $in, 'turnover_out' => $out, 'opened_at' => $opened,
            'restrictions' => json_encode($restrictions, JSON_UNESCAPED_UNICODE),
            'created_at' => now(), 'updated_at' => now(),
        ];
    }

    /** Border crossings (personal life — special personal data, ShMQ 25). */
    private function borderCrossings(array $persons, callable $pinflOf): void
    {
        $rows = [];
        $air = 'Toshkent xalqaro aeroporti';

        // Demo debtor: Turkey trip (returned), then left for Russia on 2026-08-14 and has NOT returned.
        foreach ([
            ['exit', '2026-03-02 06:40', 'Turkiya', $air],
            ['entry', '2026-03-16 22:15', 'Turkiya', $air],
            ['exit', '2026-08-14 04:55', 'Rossiya', $air],
        ] as [$dir, $at, $country, $cp]) {
            $rows[] = $this->crossingRow(self::DEBTOR, $dir, $at, $country, $cp);
        }

        // Sobirov: frequent trips to Kazakhstan by land, all returned.
        foreach ([['2026-04-10 09:20', '2026-04-13 18:05'], ['2026-07-01 08:00', '2026-07-03 20:30']] as [$out, $back]) {
            $rows[] = $this->crossingRow($pinflOf('Sobirov'), 'exit', $out, "Qozog'iston", 'Oybek CHP');
            $rows[] = $this->crossingRow($pinflOf('Sobirov'), 'entry', $back, "Qozog'iston", 'Oybek CHP');
        }

        $taken = [self::DEBTOR, $pinflOf('Sobirov')];
        foreach ($persons as $p) {
            if (in_array($p['pinfl'], $taken, true) || mt_rand(1, 100) > 40) {
                continue;
            }
            $trips = mt_rand(1, 3);
            $cursor = Carbon::create(2025, mt_rand(1, 6), mt_rand(1, 28), mt_rand(5, 22), 0);
            for ($t = 0; $t < $trips; $t++) {
                [$cp, $countries] = self::CHECKPOINTS[mt_rand(0, count(self::CHECKPOINTS) - 1)];
                $country = $countries[mt_rand(0, count($countries) - 1)];
                $out = $cursor->copy()->addDays(mt_rand(20, 120));
                $back = $out->copy()->addDays(mt_rand(3, 21))->addHours(mt_rand(1, 12));
                if ($back->isAfter(Carbon::create(2026, 11, 1))) {
                    break;
                }
                $rows[] = $this->crossingRow($p['pinfl'], 'exit', $out->format('Y-m-d H:i'), $country, $cp);
                $rows[] = $this->crossingRow($p['pinfl'], 'entry', $back->format('Y-m-d H:i'), $country, $cp);
                $cursor = $back;
            }
        }

        MockBorderCrossing::insert($rows);
    }

    private function crossingRow(string $pinfl, string $direction, string $at, string $country, string $checkpoint): array
    {
        return [
            'pinfl' => $pinfl, 'direction' => $direction, 'crossed_at' => $at.':00', 'country' => $country,
            'checkpoint' => $checkpoint, 'created_at' => now(), 'updated_at' => now(),
        ];
    }

    private function vin(): string
    {
        $chars = 'ABCDEFGHJKLMNPRSTUVWXYZ0123456789';
        $vin = 'XWB';
        for ($i = 0; $i < 14; $i++) {
            $vin .= $chars[mt_rand(0, strlen($chars) - 1)];
        }

        return $vin;
    }
}
