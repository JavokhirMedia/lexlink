<?php

namespace Tests\Feature\Ai;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AiRoutesTest extends TestCase
{
    use InteractsWithAiFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['lexlink.ai.gemini_key' => null]);
        Http::fake();
        $this->loadLaws(['oila-kodeksi', 'advokatura', 'fuqarolik-protsessual-kodeksi']);
    }

    public function test_assistant_page_and_ask_endpoint(): void
    {
        $advocate = $this->advocate();

        $this->actingAs($advocate)->get(route('ai.assistant'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('ai/assistant')->has('laws', 3)->where('ai_enabled', false));

        $this->actingAs($advocate)->postJson(route('ai.assistant.ask'), ['question' => 'Uch bola uchun aliment qancha?'])
            ->assertOk()
            ->assertJsonStructure(['answer', 'citations' => [['law', 'article', 'title', 'url']], 'disclaimer', 'model']);
    }

    public function test_claim_page_generate_and_view(): void
    {
        $this->mock(BillingService::class)->shouldReceive('chargeAi')->once()->andReturn(5000);
        $advocate = $this->advocate();
        $case = $this->alimonyCase($advocate);
        $this->completedRequest($case, $advocate);

        $this->actingAs($advocate)->get(route('ai.claim.show', $case))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('ai/claim')
                ->where('has_salary', true)->where('children_detected', 2)->where('draft', null)
                ->where('alimony_preview.average_monthly', 4_166_667));

        $this->actingAs($advocate)->post(route('ai.claim.store', $case), ['children' => 2])
            ->assertRedirect(route('ai.claim.show', $case));

        $this->actingAs($advocate)->get(route('ai.claim.show', $case))
            ->assertInertia(fn (Assert $page) => $page->where('draft.kind', 'draft_claim')
                ->where('draft.output', fn ($o) => str_contains($o, self::CLIENT_NAME))
                ->has('draft.citations'));
    }

    public function test_reanalyze_and_access_control(): void
    {
        $advocate = $this->advocate();
        $case = $this->alimonyCase($advocate);
        $request = $this->completedRequest($case, $advocate);

        $this->actingAs($advocate)->postJson(route('ai.requests.analyze', $request))
            ->assertOk()->assertJsonPath('analysis.kind', 'summary')->assertJsonPath('analysis.structured.alimony.children', 2);

        $stranger = User::factory()->create(['role' => UserRole::Lawyer]);
        $this->actingAs($stranger)->get(route('ai.claim.show', $case))->assertForbidden();
        $this->actingAs($stranger)->postJson(route('ai.requests.analyze', $request))->assertForbidden();
    }
}
