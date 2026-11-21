<?php

namespace Tests\Feature\Ai\Anonymization;

use App\Enums\DataRequestType;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Realistic, fully fictional payloads for the 8 request types (mock-gov shapes) with every kind of
 * identifier, plus a capturing Gemini fake.
 */
trait AnonymizationFixtures
{
    /** @var list<string> every outgoing Gemini request body */
    protected array $sentBodies = [];

    /** Raw identifiers that must never leave the server (fictional). */
    protected const SECRETS = [
        // people
        'Qodirov', 'Qodirova', 'Jasur', 'Malika', 'Anvarovich', 'Rustamovna', 'Saidov', 'Bekzod', 'Olimovich', 'Ergasheva', 'Nurmatov',
        // ids
        '31507901234567', '315*********67', '41203851234511', 'AC1234567', 'AC 1234567', '901112233', '111-22-33', 'qodirov.j@example.uz',
        // organisations
        'Toshkent Tekstil', 'Yangi Yoʻl Savdo', 'Namuna Bank', 'Ishonch Kredit', 'Karimov Savdo Servis', 'Rustamova va hamkorlar',
        // TIN / accounts / plates / VIN / cadastral / addresses
        '301234567', '302345678', '310457812', '20206000123456784417', '20206000****4417', '8600123412341234',
        '01 A 123 BC', '01A123BC', 'XWBJA69V9MA', '10:09:03:01:01:0123', 'Yunusobod 4-mavze', '17-uy', 'Chilonzor 9-kvartal',
        // case / decision / order / request / licence numbers, birth date
        '2-1001-2610/0398', '2-1001-2611/0457', '2-1001-2026/15', 'ORD-2026-000457', 'GOV-2026-000123', 'AL-00777', '1990-05-12', '12.05.1990',
    ];

    /** Context-free patterns that must not appear either. */
    protected const PATTERNS = [
        'pinfl' => '/(?<!\d)\d{14}(?!\d)/',
        'passport' => '/(?<![A-Z\d])[A-Z]{2}\s?\d{7}(?!\d)/',
        'plate' => '/(?<![A-Z\d])\d{2}\s?[A-Z]\s?\d{3}\s?[A-Z]{2}(?![A-Z\d])/',
        'cadastral' => '/\d{2}:\d{2}:\d{2}:\d{2}/',
        'account' => '/(?<!\d)\d{16,20}(?!\d)/',
        'email' => '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/',
        'case_number' => '/\d-\d{4}-\d{4}\/\d{2,4}/',
        'order' => '/ORD-\d{4}-\d+/',
        'phone' => '/\+998/',
    ];

    protected function captureGemini(string $text = '**Xulosa.** [SHAXS_1] boʻyicha tahlil [1].'): void
    {
        config(['lexlink.ai.gemini_key' => 'test-key', 'lexlink.ai.gemini_model' => 'gemini-test-flash']);
        $this->sentBodies = [];
        Http::fake(function (Request $r) use ($text) {
            $this->sentBodies[] = $r->body();

            return str_contains($r->url(), 'batchEmbedContents')
                ? Http::response(['embeddings' => array_map(fn () => ['values' => [1, 0, 0]], $r['requests'] ?? [[]])])
                : Http::response([
                    'candidates' => [['content' => ['parts' => [['text' => $text]]], 'finishReason' => 'STOP']],
                    'usageMetadata' => ['totalTokenCount' => 100],
                    'modelVersion' => 'gemini-test-flash',
                ]);
        });
    }

    /** JSON-decoded text parts of all bodies (so unicode escapes do not hide anything). */
    protected function sentText(): string
    {
        return implode("\n=====\n", array_map(function (string $b) {
            $json = json_decode($b, true);

            return $b."\n".json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, $this->sentBodies));
    }

    protected function assertNothingIdentifyingSent(): void
    {
        $this->assertNotEmpty($this->sentBodies, 'expected at least one Gemini call');
        $text = $this->sentText();
        $lower = mb_strtolower(str_replace(['ʻ', 'ʼ', '’', '‘', '`'], "'", $text));
        foreach (self::SECRETS as $needle) {
            $n = mb_strtolower(str_replace(['ʻ', 'ʼ', '’', '‘', '`'], "'", $needle));
            $this->assertStringNotContainsString($n, $lower, "PII leaked to Gemini: {$needle}");
        }
        foreach (self::PATTERNS as $name => $re) {
            $this->assertDoesNotMatchRegularExpression($re, $text, "PII pattern leaked to Gemini: {$name}");
        }
    }

    protected function payloadFor(DataRequestType $type): array
    {
        return match ($type) {
            DataRequestType::Salary => [
                'employers' => [['name' => '"Toshkent Tekstil" MChJ', 'tin' => '301234567'], ['name' => '"Yangi Yoʻl Savdo" MChJ', 'tin' => '302345678']],
                'months' => [
                    ['period' => '2026-01', 'employer' => '"Toshkent Tekstil" MChJ', 'amount' => 4_000_000, 'pension_contribution' => 4000],
                    ['period' => '2026-02', 'employer' => '"Toshkent Tekstil" MChJ', 'amount' => 4_000_000, 'pension_contribution' => 4000],
                    ['period' => '2026-03', 'employer' => '"Toshkent Tekstil" MChJ', 'amount' => 4_200_000, 'pension_contribution' => 4200],
                    ['period' => '2026-04', 'employer' => '"Toshkent Tekstil" MChJ', 'amount' => 4_200_000, 'pension_contribution' => 4200],
                    ['period' => '2026-05', 'employer' => '"Toshkent Tekstil" MChJ', 'amount' => 4_400_000, 'pension_contribution' => 4400],
                    ['period' => '2026-06', 'employer' => '"Toshkent Tekstil" MChJ', 'amount' => 3_200_000, 'pension_contribution' => 3200],
                    ['period' => '2026-06', 'employer' => '"Yangi Yoʻl Savdo" MChJ', 'amount' => 1_000_000, 'pension_contribution' => 1000],
                ],
                'total' => 25_000_000, 'average' => 4_166_667, 'period' => ['from' => '2026-01', 'to' => '2026-06'],
            ],
            DataRequestType::Property => ['objects' => [
                ['cadastral_number' => '10:09:03:01:01:0123', 'type' => 'Kvartira', 'address' => 'Toshkent sh., Yunusobod 4-mavze, 17-uy', 'area' => 61.5, 'share' => '1/1', 'registered_at' => '2016-08-30',
                    'encumbrances' => [['type' => 'Ipoteka', 'holder' => '"Namuna Bank" ATB', 'since' => '2016-08-30']]],
                ['cadastral_number' => '10:09:05:02:03:0456', 'type' => 'Hovli', 'address' => 'Toshkent sh., Chilonzor 9-kvartal', 'area' => 240.0, 'share' => '1/2', 'registered_at' => '2019-04-11', 'encumbrances' => []],
            ]],
            DataRequestType::Vehicle => ['vehicles' => [
                ['model' => 'Chevrolet Cobalt', 'year' => 2021, 'plate' => '01 A 123 BC', 'color' => 'Oq', 'vin' => 'XWBJA69V9MA******', 'registered_at' => '2021-06-04',
                    'pledge' => ['holder' => '"Ishonch Kredit" ATB', 'since' => '2022-03-11']],
            ]],
            DataRequestType::CourtCase => [
                'case_number' => '2-1001-2610/0398', 'court' => 'Toshkent shahar Yunusobod tumanlararo fuqarolik ishlari sudi', 'judge' => 'Ergasheva M. T.',
                'category' => 'Aliment undirish', 'status' => 'Ish yuritishda',
                'parties' => [['role' => "Da'vogar", 'name' => 'Qodirova M. R.', 'pinfl' => '412*********11'], ['role' => 'Javobgar', 'name' => 'Qodirov J. A.', 'pinfl' => '315*********67']],
                'hearings' => [['date' => '2026-12-03', 'time' => '14:30', 'room' => '12-xona', 'note' => 'Asosiy sud majlisi']],
                'decisions' => [['number' => '2-1001-2611/0457', 'date' => '2026-11-10', 'type' => 'ajrim', 'summary' => "Javobgarning ish haqi ma'lumotlarini talab qilib olish to'g'risida ajrim."]],
            ],
            DataRequestType::TaxInfo => [
                'tin' => '310457812', 'tax_regime' => 'Aylanmadan olinadigan soliq', 'vat_payer' => false,
                'individual_entrepreneur' => ['status' => "YaTT sifatida ro'yxatdan o'tgan", 'activity' => 'Chakana savdo'],
                'tax_debt' => 3_450_000, 'taxes_paid_last_year' => 12_300_000, 'registered_at' => '2018-02-14',
                'participations' => [['name' => '"Karimov Savdo Servis" MChJ', 'tin' => '310457812', 'share_percent' => 40, 'status' => 'Faol']],
            ],
            DataRequestType::BusinessShares => ['companies' => [
                ['name' => '"Karimov Savdo Servis" MChJ', 'tin' => '310457812', 'share_percent' => 40.0, 'charter_capital' => 50_000_000, 'share_value' => 20_000_000, 'since' => '2022-04-18', 'status' => 'Faol'],
            ]],
            DataRequestType::BankAccounts => [
                'accounts' => [
                    ['bank' => '"Namuna Bank" ATB', 'account' => '20206000****4417', 'currency' => 'UZS', 'balance' => 18_750_000, 'turnover_in' => 31_000_000, 'turnover_out' => 29_500_000, 'opened_at' => '2016-08-30',
                        'restrictions' => [['type' => "Ijro hujjati bo'yicha hisobvaraqqa xatlov", 'by' => "Majburiy ijro byurosi Mirzo Ulug'bek tuman bo'limi", 'since' => '2026-09-05']]],
                    ['bank' => '"Ishonch Kredit" ATB', 'account' => '20206000123456784417', 'currency' => 'UZS', 'balance' => 2_100_000, 'turnover_in' => 5_000_000, 'turnover_out' => 4_000_000, 'opened_at' => '2020-01-15', 'restrictions' => []],
                ],
                'totals_by_currency' => ['UZS' => ['balance' => 20_850_000, 'turnover_in' => 36_000_000, 'turnover_out' => 33_500_000, 'accounts' => 2]],
                'turnover_period' => ['from' => '2026-05', 'to' => '2026-10', 'months' => 6],
                'as_of' => '2026-11-21',
            ],
            DataRequestType::BorderCrossing => [
                'crossings' => [
                    ['direction' => 'exit', 'date' => '2026-03-02 08:15', 'country' => 'Rossiya', 'checkpoint' => 'Toshkent xalqaro aeroporti'],
                    ['direction' => 'entry', 'date' => '2026-05-20 22:40', 'country' => 'Rossiya', 'checkpoint' => 'Toshkent xalqaro aeroporti'],
                    ['direction' => 'exit', 'date' => '2026-08-14 06:05', 'country' => "Qozog'iston", 'checkpoint' => 'Oybek CHP'],
                ],
                'currently_abroad' => true,
                'last_exit' => ['direction' => 'exit', 'date' => '2026-08-14 06:05', 'country' => "Qozog'iston", 'checkpoint' => 'Oybek CHP'],
                'days_abroad_last_12m' => 178,
                'period' => ['from' => '2026-01', 'to' => '2026-11'],
            ],
        };
    }

    /** Amounts / facts that SHOULD reach the model for each type (analysis must stay useful). */
    protected function expectedFacts(DataRequestType $type): array
    {
        return match ($type) {
            DataRequestType::Salary => ['4166667', '25000000', '2026-06'],
            DataRequestType::Property => ['61.5', 'Kvartira', 'Ipoteka', '2016-08-30', '[MANZIL_', '[KADASTR_', '[BANK_'],
            DataRequestType::Vehicle => ['Chevrolet Cobalt', '2021', '[AVTORAQAM_', '[VIN_', '2021-06-04'],
            DataRequestType::CourtCase => ['Aliment undirish', '2026-12-03', '2026-11-10', '[ISH_', '[QAROR_'],
            DataRequestType::TaxInfo => ['3450000', '12300000', '[STIR_', '[KORXONA_'],
            DataRequestType::BusinessShares => ['20000000', '50000000', '[KORXONA_', '2022-04-18'],
            DataRequestType::BankAccounts => ['18750000', '20850000', '[BANK_', '[HISOBRAQAM_', '2026-09-05'],
            DataRequestType::BorderCrossing => ['Rossiya', '2026-08-14', '178'],
        };
    }
}
