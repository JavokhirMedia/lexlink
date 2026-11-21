<?php

namespace Tests\Feature\Domains;

use App\Enums\DataRequestType;
use App\Models\Mock\MockCourtDecision;
use App\Services\Ai\PiiMasker;
use App\Services\Requests\ResponseSummarizer;
use Database\Seeders\MockGovSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MockGovDomainApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MockGovSeeder::class);
    }

    private function key(): array
    {
        return ['X-Api-Key' => config('lexlink.mock_gov.api_key')];
    }

    private function fetch(string $type, array $basis, array $period = []): array
    {
        $id = $this->postJson('/mock-gov/v1/requests', [
            'type' => $type,
            'subject_pinfl' => MockGovSeeder::DEBTOR,
            'legal_basis' => $basis,
            'period' => $period ?: null,
            'requester_license' => 'AL-00123',
        ], $this->key())->assertCreated()->json('request_id');

        Carbon::setTestNow(now()->addSeconds(9));

        return $this->getJson("/mock-gov/v1/requests/{$id}", $this->key())->assertOk()->json();
    }

    private function court(): array
    {
        return ['type' => 'court_decision', 'number' => MockGovSeeder::DEMO_DECISION_2];
    }

    private function order(): array
    {
        return ['type' => 'advocate_order', 'number' => 'ORD-2026/0101'];
    }

    public function test_tax_info_shape(): void
    {
        $json = $this->fetch('tax_info', $this->order());

        $this->assertSame('completed', $json['status']);
        $d = $json['data'];
        $this->assertSame(['tin', 'tax_regime', 'vat_payer', 'individual_entrepreneur', 'tax_debt', 'taxes_paid_last_year', 'registered_at', 'participations'], array_keys($d));
        $this->assertSame(1_200_000, $d['tax_debt']);
        $this->assertSame(['status', 'activity'], array_keys($d['individual_entrepreneur']));
        $this->assertNull($d['individual_entrepreneur']['activity']);
        $this->assertSame('"Karimov Savdo Servis" MChJ', $d['participations'][0]['name']);
    }

    public function test_business_shares_shape(): void
    {
        $c = $this->fetch('business_shares', $this->order())['data']['companies'];

        $this->assertCount(1, $c);
        $this->assertSame(['name', 'tin', 'share_percent', 'charter_capital', 'share_value', 'since', 'status'], array_keys($c[0]));
        $this->assertEquals(40.0, $c[0]['share_percent']);
        $this->assertSame(20_000_000, $c[0]['share_value']);
    }

    public function test_bank_accounts_shape(): void
    {
        $d = $this->fetch('bank_accounts', $this->court())['data'];

        $this->assertCount(2, $d['accounts']);
        $this->assertSame(['bank', 'account', 'currency', 'balance', 'turnover_in', 'turnover_out', 'opened_at', 'restrictions'], array_keys($d['accounts'][0]));
        $this->assertStringContainsString('****', $d['accounts'][0]['account']);
        $this->assertSame('"Namuna Bank" ATB', $d['accounts'][0]['bank']);
        $this->assertSame(18_436_500, $d['totals_by_currency']['UZS']['balance']);
        $this->assertSame(2_350, $d['totals_by_currency']['USD']['balance']);
    }

    public function test_border_crossing_shape_and_period_filter(): void
    {
        $d = $this->fetch('border_crossing', $this->court())['data'];

        $this->assertCount(3, $d['crossings']);
        $this->assertSame(['direction', 'date', 'country', 'checkpoint'], array_keys($d['crossings'][0]));
        $this->assertTrue($d['currently_abroad']);
        $this->assertSame('Rossiya', $d['last_exit']['country']);
        $this->assertStringStartsWith('2026-08-14', $d['last_exit']['date']);
        $this->assertSame('Toshkent xalqaro aeroporti', $d['last_exit']['checkpoint']);
        $this->assertGreaterThan(90, $d['days_abroad_last_12m']);

        Carbon::setTestNow();
        $filtered = $this->fetch('border_crossing', $this->court(), ['from' => '2026-08', 'to' => '2026-11'])['data'];
        $this->assertCount(1, $filtered['crossings']);
        $this->assertTrue($filtered['currently_abroad']); // derived from the full history, not the window
    }

    public static function courtOnly(): array
    {
        return [
            'bank_accounts' => ['bank_accounts', 'Bank siri'],
            'border_crossing' => ['border_crossing', 'shaxsiy hayot'],
            'salary' => ['salary', 'soliq siri'],
        ];
    }

    #[DataProvider('courtOnly')]
    public function test_agency_rejects_court_only_types_with_advocate_order(string $type, string $fragment): void
    {
        $json = $this->fetch($type, $this->order());

        $this->assertSame('rejected', $json['status']);
        $this->assertStringContainsString($fragment, $json['rejection_reason']);
        $this->assertStringContainsString('Advokat orderi yetarli emas', $json['rejection_reason']);
    }

    public function test_decision_0457_does_not_open_bank_secret_at_agency(): void
    {
        $json = $this->fetch('bank_accounts', ['type' => 'court_decision', 'number' => MockGovSeeder::DEMO_DECISION]);

        $this->assertSame('rejected', $json['status']);
        $this->assertStringContainsString('ruxsat bermaydi', $json['rejection_reason']);
    }

    public function test_seeded_decisions_only_use_valid_types(): void
    {
        $valid = array_map(fn ($t) => $t->value, DataRequestType::cases());
        $decision = $this->getJson('/mock-gov/v1/court/decisions/'.MockGovSeeder::DEMO_DECISION_2, $this->key())->assertOk()
            ->assertJsonPath('allowed_types', ['bank_accounts', 'border_crossing'])
            ->assertJsonPath('expires_at', '2027-05-10');
        $this->assertNotEmpty($decision->json('number'));

        foreach (MockCourtDecision::all() as $d) {
            $this->assertSame([], array_diff($d->allowed_types, $valid), $d->number);
        }
    }

    public function test_summarizer_computes_border_and_bank_numbers(): void
    {
        $now = Carbon::parse('2026-11-21 12:00');
        Carbon::setTestNow($now);
        $s = app(ResponseSummarizer::class);

        $border = $s->summarize(DataRequestType::BorderCrossing, ['data' => ['crossings' => [
            ['direction' => 'exit', 'date' => '2025-10-01 10:00', 'country' => 'Turkiya', 'checkpoint' => 'A'], // window starts 2025-11-21
            ['direction' => 'entry', 'date' => '2025-12-01 10:00', 'country' => 'Turkiya', 'checkpoint' => 'A'],
            ['direction' => 'exit', 'date' => '2026-11-11 08:00', 'country' => 'Rossiya', 'checkpoint' => 'A'],
        ]]]);
        $this->assertTrue($border['currently_abroad']);
        $this->assertSame(10, $border['days_abroad_current']);
        $this->assertSame(10 + 10, $border['days_abroad_last_12m']);
        $this->assertSame(['Turkiya', 'Rossiya'], $border['countries']);

        $bank = $s->summarize(DataRequestType::BankAccounts, ['data' => ['accounts' => [
            ['bank' => 'X', 'currency' => 'UZS', 'balance' => 100, 'turnover_in' => 10, 'turnover_out' => 5, 'restrictions' => []],
            ['bank' => 'Y', 'currency' => 'UZS', 'balance' => 50, 'turnover_in' => 1, 'turnover_out' => 1, 'restrictions' => [['type' => 'xatlov']]],
            ['bank' => 'Y', 'currency' => 'USD', 'balance' => 7, 'turnover_in' => 0, 'turnover_out' => 0, 'restrictions' => []],
        ]]]);
        $this->assertSame(150, $bank['totals_by_currency']['UZS']['balance']);
        $this->assertSame(7, $bank['totals_by_currency']['USD']['balance']);
        $this->assertSame(2, $bank['banks_count']);
        $this->assertSame(1, $bank['restricted_count']);

        $shares = $s->summarize(DataRequestType::BusinessShares, ['data' => ['companies' => [
            ['share_percent' => 40, 'charter_capital' => 50_000_000, 'status' => 'Faol'],
            ['share_percent' => 25, 'charter_capital' => 10_000_000, 'status' => "Faoliyati to'xtatilgan"],
        ]]]);
        $this->assertSame(22_500_000, $shares['total_share_value']);
        $this->assertSame(1, $shares['active_count']);

        $tax = $s->summarize(DataRequestType::TaxInfo, ['data' => ['tax_debt' => 1_200_000, 'individual_entrepreneur' => ['status' => 'YaTT emas', 'activity' => null]]]);
        $this->assertSame(1_200_000, $tax['tax_debt']);
        $this->assertFalse($tax['is_individual_entrepreneur']);
    }

    public function test_pii_masker_masks_bank_accounts(): void
    {
        $m = new PiiMasker;
        $text = $m->mask('Hisobvaraq 20208000900123456001 va 20206000****4417, JShShIR 31207870261012');
        $this->assertStringNotContainsString('20208000900123456001', $text);
        $this->assertStringNotContainsString('4417', $text);
        $this->assertStringContainsString('[HISOBRAQAM_1]', $text);
        $this->assertStringContainsString('[JSHSHIR_1]', $text);

        $arr = $m->maskArray(['accounts' => [['bank' => '"Namuna Bank" ATB', 'account' => '20206840****0932']], 'companies' => [['name' => '"Sharq" MChJ']]]);
        $this->assertMatchesRegularExpression('/^\[HISOBRAQAM_\d+\]$/', $arr['accounts'][0]['account']);
        $this->assertSame('"Sharq" MChJ', $arr['companies'][0]['name']);
        $this->assertStringNotContainsString('900123456', implode(' ', $m->displayMap()));
    }
}
