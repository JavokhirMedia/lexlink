<?php

namespace Tests\Feature\Domains;

use App\Enums\AuditAction;
use App\Enums\DataRequestStatus;
use App\Enums\LegalBasisType;
use App\Jobs\FetchGovResultJob;
use App\Models\AiAnalysis;
use App\Models\AuditLog;
use App\Models\CaseMember;
use App\Models\DataRequest;
use App\Models\LegalBasis;
use App\Models\LegalCase;
use App\Models\Plan;
use App\Models\User;
use App\Services\Ai\AiService;
use App\Services\Ai\FallbackAiService;
use App\Services\DataRequestService;
use App\Services\Gov\GovDataProvider;
use App\Services\Gov\MockGovProvider;
use Database\Seeders\MockGovSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * End-to-end for the LegalMatrix domain types: LexLink -> real MockGovProvider (HTTP faked into the
 * app kernel) -> /mock-gov/v1 agency -> ResponseSummarizer -> fallback AI summary.
 */
class DomainRequestsTest extends TestCase
{
    use RefreshDatabase;

    private User $advocate;

    private LegalCase $case;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MockGovSeeder::class);
        Storage::fake('local');
        $this->withoutVite();

        config(['lexlink.mock_gov.base_url' => 'http://mock-gov.test/mock-gov/v1']);
        $this->app->bind(GovDataProvider::class, MockGovProvider::class);
        $this->app->bind(AiService::class, FallbackAiService::class);

        // Route the provider's HTTP calls into this application's /mock-gov/v1 routes.
        Http::fake(function (HttpRequest $req) {
            if (! str_starts_with($req->url(), 'http://mock-gov.test/')) {
                return Http::response(['error' => 'offline'], 503);
            }
            $path = parse_url($req->url(), PHP_URL_PATH);
            $resp = $this->call($req->method(), $path, [], [], [], [
                'HTTP_X_API_KEY' => $req->header('X-Api-Key')[0] ?? '',
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_TYPE' => 'application/json',
            ], $req->body() ?: null);

            return Http::response($resp->getContent(), $resp->getStatusCode(), ['Content-Type' => 'application/json']);
        });

        Plan::create(['code' => 'start', 'name' => 'Start', 'audience' => 'Advokat', 'price' => 0, 'included_requests' => 100, 'extra_price' => 0, 'included_ai' => 0]);

        $this->advocate = User::factory()->advocate('active', 'AL-00123')->create([
            'name' => 'Rustamova Dilnoza Akmalovna',
            'pinfl' => MockGovSeeder::ADVOCATE,
        ]);
        $this->case = LegalCase::create(['owner_id' => $this->advocate->id, 'title' => 'Aliment undirish', 'client_name' => 'Karimova Nilufar Shavkatovna']);
        CaseMember::create(['case_id' => $this->case->id, 'user_id' => $this->advocate->id, 'role' => 'advocate', 'can_view_responses' => true]);
    }

    private function decision0458(): LegalBasis
    {
        return LegalBasis::create([
            'case_id' => $this->case->id,
            'created_by' => $this->advocate->id,
            'type' => LegalBasisType::CourtDecision,
            'number' => MockGovSeeder::DEMO_DECISION_2,
            'court_name' => 'Toshkent shahar Yunusobod tumanlararo fuqarolik ishlari sudi',
            'issued_at' => '2026-11-10',
            'expires_at' => '2027-05-10',
            'subject_pinfl' => MockGovSeeder::DEBTOR,
            'subject_name' => 'Karimov Aziz Botirovich',
            'allowed_types' => ['bank_accounts', 'border_crossing'],
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);
    }

    private function advocateOrder(array $types = ['tax_info', 'business_shares', 'property', 'vehicle', 'court_case']): LegalBasis
    {
        return LegalBasis::create([
            'case_id' => $this->case->id,
            'created_by' => $this->advocate->id,
            'type' => LegalBasisType::AdvocateOrder,
            'number' => 'ORD-2026/0101',
            'issued_at' => now()->subDay(),
            'expires_at' => now()->addMonths(3),
            'subject_pinfl' => MockGovSeeder::DEBTOR,
            'subject_name' => 'Karimov Aziz Botirovich',
            'allowed_types' => $types,
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);
    }

    private function draft(string $type, array $params = []): DataRequest
    {
        return app(DataRequestService::class)->create($this->case, $this->advocate, [
            'type' => $type,
            'subject_pinfl' => MockGovSeeder::DEBTOR,
            'subject_name' => 'Karimov Aziz Botirovich',
            'params' => $params,
            'purpose' => "Aliment bo'yicha qarzdorning mol-mulki va joylashuvini aniqlash",
            'request_text' => "Rasmiy so'rov matni",
        ]);
    }

    private function submitAndFetch(DataRequest $r): DataRequest
    {
        app(DataRequestService::class)->signAndSubmit($r, $this->advocate);
        $this->travel(10)->seconds();
        FetchGovResultJob::dispatchSync($r->id);

        return $r->fresh(['response']);
    }

    public static function happyPaths(): array
    {
        return [
            'bank_accounts via court decision 0458' => ['bank_accounts', 'court'],
            'border_crossing via court decision 0458' => ['border_crossing', 'court'],
            'tax_info via advocate order' => ['tax_info', 'order'],
            'business_shares via advocate order' => ['business_shares', 'order'],
        ];
    }

    #[DataProvider('happyPaths')]
    public function test_new_type_happy_path(string $type, string $basis): void
    {
        $basis === 'court' ? $this->decision0458() : $this->advocateOrder();

        $r = $this->draft($type);
        $this->assertTrue(app(DataRequestService::class)->checkBasis($r)['ok']);

        $r = $this->submitAndFetch($r);

        $this->assertSame(DataRequestStatus::Completed, $r->status, (string) $r->rejection_reason.$r->failure_reason);
        $this->assertSame($type, $r->response->summary['type']);
        $this->assertNotEmpty($r->response->payload['data']);

        $analysis = AiAnalysis::where('data_request_id', $r->id)->where('kind', 'summary')->first();
        $this->assertNotNull($analysis);
        $this->assertStringContainsString('**Xulosa.**', $analysis->output);
    }

    public function test_bank_and_border_numbers_and_ai_summary_for_demo_debtor(): void
    {
        $this->decision0458();

        $bank = $this->submitAndFetch($this->draft('bank_accounts'));
        $s = $bank->response->summary;
        $this->assertSame(2, $s['accounts_count']);
        $this->assertSame(18_436_500, $s['totals_by_currency']['UZS']['balance']);
        $this->assertSame(2_350, $s['totals_by_currency']['USD']['balance']);
        $bankAi = AiAnalysis::where('data_request_id', $bank->id)->firstOrFail();
        $this->assertStringContainsString('18 436 500', $bankAi->output);
        $this->assertStringNotContainsString('20206000****4417', json_encode($bankAi->output, JSON_UNESCAPED_UNICODE));

        $border = $this->submitAndFetch($this->draft('border_crossing'));
        $b = $border->response->summary;
        $this->assertTrue($b['currently_abroad']);
        $this->assertSame('2026-08-14', $b['last_exit_date']);
        $this->assertSame('Rossiya', $b['last_exit_country']);
        $this->assertSame(3, $b['crossings_count']);
        $expectedCurrent = (int) now()->startOfDay()->diffInDays(now()->setDate(2026, 8, 14)->startOfDay(), true);
        $this->assertSame($expectedCurrent, $b['days_abroad_current']);
        $this->assertSame($expectedCurrent + 14, $b['days_abroad_last_12m']);

        $ai = AiAnalysis::where('data_request_id', $border->id)->firstOrFail();
        $this->assertStringContainsString('2026-08-14 dan beri chet elda (Rossiya)', $ai->output);
        $this->assertStringContainsString('chet elga chiqishni vaqtincha cheklash', $ai->output);
    }

    public function test_tax_and_shares_numbers(): void
    {
        $this->advocateOrder();

        $tax = $this->submitAndFetch($this->draft('tax_info'))->response->summary;
        $this->assertSame(1_200_000, $tax['tax_debt']);
        $this->assertFalse($tax['is_individual_entrepreneur']);
        $this->assertSame(1, $tax['participations_count']);

        $shares = $this->submitAndFetch($this->draft('business_shares'))->response->summary;
        $this->assertSame(1, $shares['companies_count']);
        $this->assertSame(20_000_000, $shares['total_share_value']); // 40% of 50 mln
    }

    public static function courtOnly(): array
    {
        return [
            'bank_accounts' => ['bank_accounts', 'Bank siri — faqat sud qarori yoki ajrimi asosida'],
            'border_crossing' => ['border_crossing', 'faqat sud orqali'],
            'salary' => ['salary', 'Soliq siri — faqat sud qarori'],
        ];
    }

    #[DataProvider('courtOnly')]
    public function test_court_only_type_is_blocked_with_advocate_order(string $type, string $fragment): void
    {
        // even an order that (wrongly) lists the type does not open a court-only regime
        $this->advocateOrder([$type, 'tax_info']);
        $r = $this->draft($type, $type === 'salary' ? ['period_from' => '2026-05', 'period_to' => '2026-10'] : []);

        $check = app(DataRequestService::class)->checkBasis($r);
        $this->assertFalse($check['ok']);
        $this->assertStringContainsString($fragment, $check['reason']);
        $this->assertStringContainsString('advokat orderi yetarli emas', $check['reason']);

        try {
            app(DataRequestService::class)->signAndSubmit($r, $this->advocate);
            $this->fail('expected block');
        } catch (ValidationException $e) {
            $this->assertSame($check['reason'], $e->errors()['basis'][0]);
        }
        $this->assertSame(DataRequestStatus::Draft, $r->fresh()->status);
        $this->assertTrue(AuditLog::where('action', AuditAction::RequestBlocked)->where('subject_id', $r->id)->exists());
    }

    public function test_decision_0457_does_not_cover_bank_accounts(): void
    {
        LegalBasis::create([
            'case_id' => $this->case->id, 'created_by' => $this->advocate->id, 'type' => LegalBasisType::CourtDecision,
            'number' => MockGovSeeder::DEMO_DECISION, 'court_name' => 'Yunusobod sudi', 'issued_at' => '2026-11-10', 'expires_at' => '2027-05-10',
            'subject_pinfl' => MockGovSeeder::DEBTOR, 'subject_name' => 'Karimov Aziz Botirovich',
            'allowed_types' => ['salary', 'court_case'], 'verification_status' => 'verified', 'verified_at' => now(),
        ]);

        $check = app(DataRequestService::class)->checkBasis($this->draft('bank_accounts'));
        $this->assertFalse($check['ok']);
        $this->assertStringContainsString('bank hisobvaraqlari', $check['reason']);
    }

    public function test_create_page_receives_matrix_grouped_by_domain(): void
    {
        $this->advocateOrder();

        $this->actingAs($this->advocate)->get(route('requests.create', ['case' => $this->case->id]))->assertOk()
            ->assertInertia(fn ($page) => $page->component('requests/create')
                ->has('types', 8)
                ->has('matrix.domains', 5)
                ->where('matrix.domains.3.key', 'bank')
                ->where('matrix.domains.3.types.0.value', 'bank_accounts')
                ->where('matrix.domains.3.types.0.access', 'court')
                ->where('matrix.domains.3.types.0.refs.0.article', '10'));
    }
}
