<?php

namespace Tests\Feature\Ai;

use App\Enums\AuditAction;
use App\Enums\DataRequestType;
use App\Models\AiAnalysis;
use App\Models\AuditLog;
use App\Services\Ai\AiAnalysisPresenter;
use App\Services\Ai\AiService;
use App\Services\Ai\GeminiAiService;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiAiServiceTest extends TestCase
{
    use InteractsWithAiFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadLaws();
    }

    /** Every outgoing Gemini body must be free of raw PII. */
    private function assertNoPiiSent(): void
    {
        Http::assertSent(function (Request $r) {
            $body = $r->body();
            foreach ([self::SUBJECT_PINFL, 'Qodirov', 'Qodirova', 'Jasur', 'Malika', 'Saidov', 'Bekzod', '111-22-33', '315*********67'] as $needle) {
                $this->assertStringNotContainsString($needle, $body, "PII leaked to Gemini: {$needle}");
            }
            $this->assertFalse(str_contains($r->url(), 'test-key'), 'API key must not be in the URL');
            $this->assertSame('test-key', $r->header('x-goog-api-key')[0] ?? null);

            return true;
        });
    }

    public function test_service_is_bound_to_gemini(): void
    {
        $this->assertInstanceOf(GeminiAiService::class, app(AiService::class));
    }

    public function test_salary_analysis_masks_pii_computes_alimony_and_cites_articles(): void
    {
        $this->fakeGemini('**Xulosa.** [SHAXS_4] oʻrtacha daromadi barqaror [1].');
        $advocate = $this->advocate();
        $request = $this->completedRequest($this->alimonyCase($advocate), $advocate);
        $this->actingAs($advocate);

        $analysis = app(AiService::class)->analyzeResponse($request);

        Http::assertSentCount(1);
        $this->assertNoPiiSent();
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'models/gemini-test-flash:generateContent')
            && str_contains($r->body(), '[JSHSHIR_1]') && str_contains($r->body(), '[SHAXS_'));

        $this->assertSame('summary', $analysis->kind);
        $this->assertSame('gemini-test-flash', $analysis->model);
        $this->assertSame(321, $analysis->tokens);
        $this->assertSame('v1', $analysis->prompt_version);
        $this->assertSame('gemini', $analysis->structured['generator']);
        $this->assertSame(config('lexlink.ai.disclaimer'), $analysis->structured['disclaimer']);

        // numbers are computed in PHP: 25 000 000 over 6 months (two employers in June)
        $alimony = $analysis->structured['alimony'];
        $this->assertSame(6, $alimony['months_count']);
        $this->assertSame(25_000_000, $alimony['total_income']);
        $this->assertSame(4_166_667, $alimony['average_monthly']);
        $this->assertSame(2, $alimony['children']); // "2 nafar farzand" from the case description
        $this->assertSame('1/3', $alimony['selected']['share']);
        $this->assertSame(1_388_889, $alimony['selected']['amount']);

        $citations = collect($analysis->citations);
        $art99 = $citations->first(fn ($c) => $c['law'] === 'Oila kodeksi' && $c['article'] === '99-modda');
        $this->assertNotNull($art99);
        $this->assertStringStartsWith('https://lex.uz/docs/-104720#', $art99['url']);

        // stored output stays masked; presenter restores names locally
        $this->assertStringNotContainsString('Qodirov', $analysis->output);
        $presented = AiAnalysisPresenter::present($analysis);
        $this->assertArrayNotHasKey('_pii', $presented['structured']);
        $this->assertDoesNotMatchRegularExpression('/\[SHAXS_\d+\]/', $presented['output']);

        $this->assertTrue(AuditLog::where('action', AuditAction::AiAnalysis)->where('subject_id', $analysis->id)->exists());
    }

    public function test_property_analysis_summarises_assets(): void
    {
        $this->fakeGemini('**Xulosa.** 1 ta obyekt [1].');
        $advocate = $this->advocate();
        $request = $this->completedRequest($this->alimonyCase($advocate), $advocate, DataRequestType::Property, [
            'objects' => [['cadastral_number' => '10:09:03:01:01:0123', 'type' => 'Kvartira', 'address' => 'Toshkent sh., Yunusobod 4-mavze', 'area' => 61.5, 'share' => '1/1', 'encumbrances' => []]],
        ]);
        $this->actingAs($advocate);

        $analysis = app(AiService::class)->analyzeResponse($request);

        $this->assertNoPiiSent();
        Http::assertSent(fn (Request $r) => ! str_contains($r->body(), '10:09:03:01:01:0123') && ! str_contains($r->body(), 'Yunusobod 4-mavze'));
        $this->assertSame('property', $analysis->structured['type']);
        $this->assertSame(1, $analysis->structured['facts']['count']);
        $this->assertNotEmpty($analysis->citations);
    }

    public function test_request_text_is_masked_outbound_and_unmasked_for_the_advocate(): void
    {
        $this->fakeGemini("Soliq va pensiya ma'lumotlariga\n\nADVOKAT SO'ROVI\n\nAdvokat [SHAXS_1] [SHAXS_2] manfaatlarini ko'zlab ... [SHAXS_4] (JShShIR: [JSHSHIR_1]) [1].");
        $advocate = $this->advocate();
        $request = $this->completedRequest($this->alimonyCase($advocate), $advocate);
        $this->actingAs($advocate);

        $text = app(AiService::class)->draftRequestText($request);

        $this->assertNoPiiSent();
        $this->assertStringContainsString(self::ADVOCATE_NAME, $text);
        $this->assertStringContainsString(self::CLIENT_NAME, $text);
        $this->assertStringNotContainsString(self::SUBJECT_PINFL, $text); // the letter shows a masked PINFL only
        $this->assertMatchesRegularExpression('/3\*+67/', $text);
        $this->assertTrue(AiAnalysis::where('kind', 'request_text')->exists());
        Http::assertSent(fn (Request $r) => str_contains($r->body(), 'Soliq kodeksi') && str_contains($r->body(), '29-modda'));
    }

    public function test_draft_claim_is_charged_masked_and_structured(): void
    {
        $this->fakeGemini("**[SHAXS_X] tumanlararo sudiga**\n\n## DA'VO ARIZASI\n\nDa'vogar [SHAXS_2] ... [1][2]");
        $billing = $this->mock(BillingService::class);
        $billing->shouldReceive('chargeAi')->once()->withArgs(fn ($u, $kind) => $kind === 'draft_claim')->andReturn(5000);

        $advocate = $this->advocate();
        $case = $this->alimonyCase($advocate);
        $this->completedRequest($case, $advocate);
        $this->actingAs($advocate);

        $analysis = app(AiService::class)->draftClaim($case, ['user' => $advocate]);

        $this->assertNoPiiSent();
        $this->assertSame('draft_claim', $analysis->kind);
        $this->assertSame(5000, $analysis->cost);
        $this->assertSame(2, $analysis->structured['children']);
        $this->assertSame(1_388_889, $analysis->structured['alimony']['selected']['amount']);
        $laws = collect($analysis->citations)->map(fn ($c) => $c['law'].' '.$c['article']);
        $this->assertContains('Oila kodeksi 99-modda', $laws);
        $this->assertContains('FPK 189-modda', $laws);
        $this->assertStringContainsString(self::CLIENT_NAME, AiAnalysisPresenter::present($analysis)['output']);
        $this->assertTrue(AuditLog::where('action', AuditAction::AiDraftClaim)->exists());
    }

    public function test_gemini_error_falls_back_to_template(): void
    {
        config(['lexlink.ai.gemini_key' => 'test-key']);
        Http::fake(['*' => Http::response(['error' => ['message' => 'boom']], 500)]);
        $advocate = $this->advocate();
        $request = $this->completedRequest($this->alimonyCase($advocate), $advocate);
        $this->actingAs($advocate);

        $analysis = app(AiService::class)->analyzeResponse($request);

        $this->assertStringStartsWith('fallback-template', $analysis->model);
        $this->assertSame('fallback', $analysis->structured['generator']);
        $this->assertStringContainsString('1/3', $analysis->output);
        $this->assertNotEmpty($analysis->citations);
    }
}
