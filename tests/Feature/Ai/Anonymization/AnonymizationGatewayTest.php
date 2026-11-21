<?php

namespace Tests\Feature\Ai\Anonymization;

use App\Enums\AuditAction;
use App\Enums\DataRequestType;
use App\Models\AuditLog;
use App\Models\DataRequest;
use App\Models\LawChunk;
use App\Models\LegalCase;
use App\Models\User;
use App\Services\Ai\AiAnalysisPresenter;
use App\Services\Ai\AiService;
use App\Services\Ai\Anonymization\AnonymizationContext;
use App\Services\Ai\Anonymization\AnonymizationGateway;
use App\Services\Ai\Anonymization\AnonymizedPayload;
use App\Services\Ai\Anonymization\PiiLeakBlocked;
use App\Services\Ai\Anonymization\ResponseProjector;
use App\Services\Ai\FallbackAiService;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\LawKnowledgeBase;
use App\Services\BillingService;
use Error;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Feature\Ai\InteractsWithAiFixtures;
use Tests\TestCase;
use TypeError;

class AnonymizationGatewayTest extends TestCase
{
    use AnonymizationFixtures, InteractsWithAiFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadLaws();
    }

    /** Case whose free text is full of identifiers (all fictional). */
    private function richCase(User $advocate): LegalCase
    {
        $advocate->forceFill(['phone' => '+998901234500', 'email' => 'saidov.advokat@example.uz'])->save();

        return $this->alimonyCase($advocate, [
            'court_case_number' => '2-1001-2610/0398',
            'description' => 'Voyaga yetmagan 2 nafar farzand taʼminoti uchun aliment. Javobgar: Qodirov Jasur Anvarovich (JShShIR 31507901234567, '
                .'pasport AC1234567, tugʻilgan sana: 12.05.1990), tel +998 90 111-22-33, qodirov.j@example.uz, yashash manzili: Yunusobod 4-mavze, 17-uy. '
                .'"Toshkent Tekstil" MChJda ishlaydi, "Namuna Bank" ATB da hisobi bor. Order ORD-2026-000457, sud ishi 2-1001-2610/0398. Jasurning onasi ham xabardor.',
            'parties' => [['role' => 'Javobgar', 'name' => 'Qodirov J. A.', 'birth_date' => '1990-05-12']],
        ]);
    }

    private function requestWith(LegalCase $case, User $advocate, DataRequestType $type, ?array $data = null): DataRequest
    {
        $request = $this->completedRequest($case, $advocate, $type, $data ?? $this->payloadFor($type));
        $request->forceFill(['gov_request_id' => 'GOV-2026-000123'])->save();

        return $request->fresh(['response', 'legalCase', 'legalBasis']);
    }

    public static function types(): array
    {
        return array_map(fn (DataRequestType $t) => [$t], array_combine(array_map(fn ($t) => $t->value, DataRequestType::cases()), DataRequestType::cases()));
    }

    #[DataProvider('types')]
    public function test_analyze_response_sends_only_anonymised_facts(DataRequestType $type): void
    {
        $this->captureGemini();
        $advocate = $this->advocate();
        $request = $this->requestWith($this->richCase($advocate), $advocate, $type);
        $this->actingAs($advocate);

        $analysis = app(AiService::class)->analyzeResponse($request);

        $this->assertNothingIdentifyingSent();
        $sent = $this->sentText();
        foreach ($this->expectedFacts($type) as $fact) {
            $this->assertStringContainsString($fact, $sent, "analysis-relevant fact missing for {$type->value}: {$fact}");
        }

        // transparency: the exact sent text + report are persisted and exposed (never the reverse map)
        $this->assertSame('gemini-test-flash', $analysis->model);
        $this->assertNotEmpty($analysis->sent_payload);
        $this->assertStringContainsString(json_encode($analysis->sent_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $sent);
        $report = $analysis->anonymization_report;
        $this->assertSame('passed', $report['leak_check']);
        $this->assertTrue($report['ai_called']);
        $this->assertSame(['ShMQ 16-modda', 'ShMQ 15-modda'], $report['legal_basis']);
        $this->assertGreaterThan(0, collect($report['replaced'])->firstWhere('label', 'shaxs')['count'] ?? 0);
        $this->assertGreaterThan(0, collect($report['replaced'])->firstWhere('label', 'manzil')['count'] ?? 0); // description address

        $presented = AiAnalysisPresenter::present($analysis);
        $this->assertSame($analysis->sent_payload, $presented['sent_payload']);
        $this->assertSame('passed', $presented['anonymization']['leak_check']);
        $this->assertStringNotContainsString('Qodirov', json_encode($presented['anonymization'], JSON_UNESCAPED_UNICODE));
        $this->assertStringNotContainsString('31507901234567', json_encode($presented, JSON_UNESCAPED_UNICODE));
    }

    public function test_salary_prompt_keeps_event_dates_and_amounts_but_masks_identifiers(): void
    {
        $this->captureGemini();
        $advocate = $this->advocate();
        $request = $this->requestWith($this->richCase($advocate), $advocate, DataRequestType::Salary);
        $this->actingAs($advocate);

        $analysis = app(AiService::class)->analyzeResponse($request);
        $sent = $analysis->sent_payload;

        foreach (['[ISH_BERUVCHI_1]', '[ISH_BERUVCHI_2]', '[STIR_', '[JSHSHIR_1]', '[PASPORT_1]', '[TELEFON_1]', '[EMAIL_1]', '[TUGILGAN_SANA_1]', '[ISH_1]', '[ORDER_1]'] as $ph) {
            $this->assertStringContainsString($ph, $sent);
        }
        $this->assertStringContainsString('2026-01', $sent);
        $this->assertStringContainsString('4166667', $sent); // PHP-computed average
        $this->assertStringNotContainsString('1990', $sent);   // birth date masked
        $labels = collect($analysis->anonymization_report['replaced'])->pluck('label');
        $this->assertContains('tashkilot', $labels);
        $this->assertContains("tug'ilgan sana", $labels);
    }

    public function test_request_text_claim_and_ask_law_are_anonymised(): void
    {
        $this->captureGemini('Advokat [SHAXS_1] (litsenziya № [LITSENZIYA_1]) [1].');
        $this->mock(BillingService::class)->shouldReceive('chargeAi')->andReturn(0);
        $advocate = $this->advocate();
        $case = $this->richCase($advocate);
        $salary = $this->requestWith($case, $advocate, DataRequestType::Salary);
        $this->requestWith($case, $advocate, DataRequestType::BankAccounts);
        $this->requestWith($case, $advocate, DataRequestType::Property);
        $this->actingAs($advocate);

        // draft request text
        $text = app(AiService::class)->draftRequestText($salary);
        $this->assertNothingIdentifyingSent();
        $this->assertStringContainsString('AL-00777', $text);           // restored locally for the advocate
        $this->assertStringContainsString(self::ADVOCATE_NAME, $text);

        // draft claim (salary + bank + property responses)
        $this->sentBodies = [];
        $claim = app(AiService::class)->draftClaim($case->fresh(), ['user' => $advocate, 'court' => 'Yunusobod tumanlararo fuqarolik ishlari sudi']);
        $this->assertNothingIdentifyingSent();
        $sent = $this->sentText();
        foreach (['4166667', '18750000', '61.5', '[SUD_1]', '[SOROV_'] as $fact) {
            $this->assertStringContainsString($fact, $sent);
        }
        $this->assertStringNotContainsString('Yunusobod', $sent);
        $this->assertSame('passed', $claim->anonymization_report['leak_check']);

        // askLaw with semantic search: the query embedding is anonymised too
        LawChunk::query()->update(['embedding' => json_encode([1.0, 0.0, 0.0])]);
        app(LawKnowledgeBase::class)->flushCache();
        $this->sentBodies = [];
        $result = app(AiService::class)->askLaw('Qodirov Jasur (JShShIR 31507901234567, pasport AC1234567, +998 90 111-22-33) uchun aliment qancha?');

        $this->assertNothingIdentifyingSent();
        $this->assertTrue(collect($this->sentBodies)->contains(fn ($b) => str_contains($b, 'RETRIEVAL_QUERY')), 'query embedding call expected');
        $this->assertTrue($result['pii_removed']);
        $this->assertSame('passed', $result['anonymization']['leak_check']);
        $this->assertStringContainsString('[JSHSHIR_1]', $result['sent_payload']);
        $this->assertStringNotContainsString('31507901234567', json_encode($result, JSON_UNESCAPED_UNICODE));
    }

    public function test_leak_guard_blocks_call_audits_without_values_and_falls_back(): void
    {
        $this->captureGemini();
        $advocate = $this->advocate();
        $request = $this->requestWith($this->richCase($advocate), $advocate, DataRequestType::BankAccounts);
        $this->actingAs($advocate);

        // a value the masker cannot recognise (only the known-PII set has it) reaches the guard
        $ctx = app(AiService::class)->contextFor($request->legalCase, $request, 'analyze_response');
        $ctx->known('person_name', 'Zumradxon');
        try {
            app(AnonymizationGateway::class)->generation('system', "Fakt: Zumradxon ning hisobi.\n", $ctx);
            $this->fail('expected PiiLeakBlocked');
        } catch (PiiLeakBlocked $e) {
            $this->assertSame(['person_name' => 1], $e->categories);
            $this->assertSame('blocked', $e->report['leak_check']);
        }
        $this->assertCount(0, $this->sentBodies);

        $audit = AuditLog::where('action', AuditAction::AiPiiBlocked)->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertSame(['person_name' => 1], $audit->meta['categories']);
        $this->assertStringNotContainsString('Zumradxon', json_encode($audit->meta, JSON_UNESCAPED_UNICODE));
        $this->assertSame("AI so'rovi bloklandi (shaxsiy ma'lumot aniqlandi)", AuditAction::AiPiiBlocked->label());
    }

    public function test_raw_identifier_injected_past_the_projection_blocks_the_whole_analysis(): void
    {
        $this->captureGemini();
        // projector bypass: emits the employer's raw TIN glued into a code, so no masking rule can repair it —
        // only the leak guard's known-PII set (registered from the payload) catches it
        $this->app->instance(ResponseProjector::class, new class extends ResponseProjector
        {
            public function project(DataRequestType $type, array $data, AnonymizationContext $ctx): array
            {
                parent::project($type, $data, $ctx); // registers the known PII

                return ['ish_beruvchi_kodi' => 'kod301234567x', 'ish_beruvchi' => 'Toshkent-Tekstil'];
            }
        });
        $advocate = $this->advocate();
        $request = $this->requestWith($this->richCase($advocate), $advocate, DataRequestType::Salary);
        $this->actingAs($advocate);

        $analysis = app(AiService::class)->analyzeResponse($request);

        $this->assertCount(0, $this->sentBodies, 'Gemini must not be called when the leak guard fires');
        $this->assertStringStartsWith(FallbackAiService::MODEL, $analysis->model);
        $this->assertStringContainsString('1/3', $analysis->output); // deterministic template still answers
        $this->assertNull($analysis->sent_payload);
        $this->assertSame('blocked', $analysis->anonymization_report['leak_check']);
        $this->assertFalse($analysis->anonymization_report['ai_called']);

        $audit = AuditLog::where('action', AuditAction::AiPiiBlocked)->where('case_id', $request->case_id)->first();
        $this->assertNotNull($audit);
        $this->assertArrayHasKey('tin', $audit->meta['categories']);
        $meta = json_encode($audit->meta, JSON_UNESCAPED_UNICODE);
        foreach (['301234567', 'Toshkent', 'Tekstil', 'Qodirov'] as $value) {
            $this->assertStringNotContainsString($value, $meta);
        }
    }

    public function test_fallback_service_records_that_ai_was_not_called(): void
    {
        config(['lexlink.ai.gemini_key' => null]);
        Http::fake();
        $advocate = $this->advocate();
        $request = $this->requestWith($this->richCase($advocate), $advocate, DataRequestType::Salary);
        $this->actingAs($advocate);

        $analysis = app(AiService::class)->analyzeResponse($request);

        Http::assertNothingSent();
        $this->assertNull($analysis->sent_payload);
        $this->assertFalse($analysis->anonymization_report['ai_called']);
        $this->assertSame('not_run', $analysis->anonymization_report['leak_check']);
        $this->assertStringContainsString('AI chaqirilmadi', $analysis->anonymization_report['note']);
    }

    public function test_gemini_client_only_accepts_gateway_payloads(): void
    {
        config(['lexlink.ai.gemini_key' => 'test-key']);
        Http::fake();
        $client = app(GeminiClient::class);

        try {
            $client->generate('raw prompt with Qodirov Jasur'); // @phpstan-ignore argument.type
            $this->fail('expected TypeError');
        } catch (TypeError) {
        }
        try {
            $client->embed(['raw text']); // @phpstan-ignore argument.type
            $this->fail('expected TypeError');
        } catch (TypeError) {
        }
        try {
            $client->embedQuery('raw query'); // @phpstan-ignore argument.type
            $this->fail('expected TypeError');
        } catch (TypeError) {
        }
        try {
            new AnonymizedPayload('generation', '', 'x', [], [], 'seal'); // @phpstan-ignore new.private
            $this->fail('expected private constructor error');
        } catch (Error) {
        }

        // a forged payload (built without the gateway, e.g. via reflection) is refused before any HTTP call
        $forged = (new \ReflectionClass(AnonymizedPayload::class))->newInstanceWithoutConstructor();
        (function () {
            $this->kind = 'generation';
            $this->system = '';
            $this->text = 'Qodirov Jasur 31507901234567';
            $this->texts = [];
            $this->report = [];
            $this->seal = str_repeat('0', 64);
        })->call($forged);
        try {
            $client->generate($forged);
            $this->fail('expected refusal');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('AnonymizationGateway', $e->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_masked_lists_and_event_dates_in_free_text(): void
    {
        $ctx = AnonymizationContext::public();
        $ctx->person('Qodirov Jasur Anvarovich');
        $ctx->birthDate('1990-05-12');
        $payload = app(AnonymizationGateway::class)->generation('s',
            'Jasur 2026-11-05 da sudga keldi; Qodirovning tugʻilgan kuni 12.05.1990. Manzil: Navoiy koʻchasi 12. Karta 8600 1234 1234 1234.', $ctx);

        $this->assertStringContainsString('2026-11-05', $payload->text);
        $this->assertStringNotContainsString('1990', $payload->text);
        $this->assertStringNotContainsString('Navoiy', $payload->text);
        $this->assertStringNotContainsString('8600', $payload->text);
        $this->assertStringNotContainsString('Jasur', $payload->text);
        $this->assertStringContainsString('[MANZIL_1]', $payload->text);
    }
}
