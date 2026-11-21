<?php

namespace Tests\Feature\Ai;

use App\Services\Ai\AiAnalysisPresenter;
use App\Services\Ai\AiService;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FallbackAiServiceTest extends TestCase
{
    use InteractsWithAiFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['lexlink.ai.gemini_key' => null]);
        Http::fake();
        $this->loadLaws();
    }

    public function test_without_key_nothing_is_sent_and_templates_are_used(): void
    {
        $advocate = $this->advocate();
        $request = $this->completedRequest($this->alimonyCase($advocate), $advocate);
        $this->actingAs($advocate);

        $analysis = app(AiService::class)->analyzeResponse($request);

        Http::assertNothingSent();
        $this->assertSame('fallback-template', $analysis->model);
        $this->assertSame(0, $analysis->tokens);
        $this->assertStringContainsString('4 166 667 soʻm', $analysis->output);
        $this->assertStringContainsString('Oila kodeksi 99-modda', $analysis->output);
        $this->assertNotEmpty($analysis->citations);

        // the placeholder->real-name map is encrypted at rest (raw DB value must not contain the name)
        $raw = DB::table('ai_analyses')->where('id', $analysis->id)->value('structured');
        $this->assertStringNotContainsString(self::ADVOCATE_NAME, (string) $raw);
        $this->assertIsString(json_decode((string) $raw, true)['_pii'] ?? null);
        $this->assertNotNull(AiAnalysisPresenter::present($analysis->fresh()));

        // purge drops the map entirely
        $analysis->dataRequest->response->update(['expires_at' => now()->subMinute()]);
        $this->artisan('lexlink:purge-responses')->assertSuccessful();
        $this->assertArrayNotHasKey('_pii', (array) $analysis->fresh()->structured);

        $text = app(AiService::class)->draftRequestText($request);
        $this->assertStringContainsString('ADVOKAT SOʻROVI', $text);
        $this->assertStringContainsString(self::ADVOCATE_NAME, $text);
        $this->assertStringContainsString('7¹', $text);
    }

    public function test_fallback_claim_has_required_structure(): void
    {
        $this->mock(BillingService::class)->shouldReceive('chargeAi')->once()->andReturn(0);
        $advocate = $this->advocate();
        $case = $this->alimonyCase($advocate);
        $this->completedRequest($case, $advocate);
        $this->actingAs($advocate);

        $claim = app(AiService::class)->draftClaim($case, ['user' => $advocate, 'court' => 'Yunusobod tumanlararo fuqarolik ishlari boʻyicha sudi']);

        Http::assertNothingSent();
        $this->assertNull($claim->sent_payload);
        $this->assertFalse($claim->anonymization_report['ai_called']);
        $this->assertStringContainsString('AI chaqirilmadi', $claim->anonymization_report['note']);
        $output = AiAnalysisPresenter::present($claim)['output']; // stored output stays masked ([SUD_1]); restored for display
        foreach (['Yunusobod tumanlararo', 'Daʼvogar:', 'Javobgar:', 'DAʼVO ARIZASI', 'Daʼvo mazmuni', 'Huquqiy asoslar', 'SOʻRAYMAN', 'Ilovalar', '1/3'] as $part) {
            $this->assertStringContainsString($part, $output);
        }
    }

    public function test_ask_law_returns_citations_with_lex_links(): void
    {
        $result = app(AiService::class)->askLaw('Bir bola uchun aliment miqdori qancha?');

        Http::assertNothingSent();
        $this->assertSame('fallback-template', $result['model']);
        $this->assertSame(config('lexlink.ai.disclaimer'), $result['disclaimer']);
        $this->assertNotEmpty($result['citations']);
        $this->assertSame('99-modda', $result['citations'][0]['article']);
        $this->assertStringStartsWith('https://lex.uz/docs/-104720#-', $result['citations'][0]['url']);
        $this->assertStringContainsString('toʻrtdan bir', $result['answer']);
    }
}
