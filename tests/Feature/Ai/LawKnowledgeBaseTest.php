<?php

namespace Tests\Feature\Ai;

use App\Models\LawChunk;
use App\Services\Ai\LawKnowledgeBase;
use App\Services\Ai\Lex\LexParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LawKnowledgeBaseTest extends TestCase
{
    use InteractsWithAiFixtures, RefreshDatabase;

    public function test_parser_reads_lex_uz_markup_with_superscript_articles(): void
    {
        $html = '<html><head><title>&nbsp;349-I-сон 27.12.1996.&nbsp;Advokatura toʻgʻrisida</title></head><body><div id="divCont">'
            .'<div class="ACT_TITLE lx_elem"><div class="lx_elem2"><div class="lx_elem3"><span>Ҳужжатга таклиф юбориш</span></div></div><div name="-1" id="-1">ADVOKATURA TOʻGʻRISIDA</div></div>'
            .'<div class="TEXT_HEADER_DEFAULT lx_elem"><div name="-2" id="-2">1-bob. Umumiy qoidalar</div></div>'
            .'<div class="CLAUSE_DEFAULT lx_elem"><div name="-3" id="-3">7<sup>1</sup>-modda. Advokat soʻrovi</div></div>'
            .'<div class="ACT_TEXT lx_elem"><div name="-4" id="-4">Advokat soʻrov yuborishga haqli.</div></div>'
            .'<div class="BY_DEFAULT lx_elem"><div name="-5" id="-5"></div></div>'
            .'<div class="ACT_TEXT lx_elem"><div name="-6" id="-6">Oʻn besh kun ichida javob beriladi.</div></div>'
            .'<div class="CLAUSE_DEFAULT lx_elem"><div name="-7" id="-7">8-modda. Advokatning yordamchisi</div></div>'
            .'<div class="ACT_TEXT lx_elem"><div name="-8" id="-8">Matn.</div></div>'
            .'</div></body></html>';

        $doc = (new LexParser)->parse($html);

        $this->assertSame('349-I', $doc['number']);
        $this->assertSame('1996-12-27', $doc['adopted_at']);
        $this->assertCount(2, $doc['articles']);
        $this->assertSame('7-1', $doc['articles'][0]['number']);
        $this->assertSame('Advokat soʻrovi', $doc['articles'][0]['title']);
        $this->assertSame('-3', $doc['articles'][0]['anchor']);
        $this->assertSame('1-bob. Umumiy qoidalar', $doc['articles'][0]['chapter']);
        $this->assertSame("Advokat soʻrov yuborishga haqli.\nOʻn besh kun ichida javob beriladi.", $doc['articles'][0]['text']);
    }

    public function test_import_from_snapshot_loads_the_committed_laws(): void
    {
        $this->artisan('lex:import', ['--from-snapshot' => true, '--only' => ['oila-kodeksi', 'advokatura']])->assertSuccessful();

        $kb = app(LawKnowledgeBase::class);
        $art = $kb->article('oila-kodeksi', '99');
        $this->assertNotNull($art);
        $this->assertStringContainsString('toʻrtdan bir qismi', $art->content);
        $this->assertStringContainsString('uchdan bir qismi', $art->content);
        $this->assertStringContainsString('yarmisi', $art->content);
        $this->assertSame(['law' => 'Oila kodeksi', 'article' => '99-modda', 'title' => 'Ota-onaning voyaga yetmagan bolalariga toʻlaydigan aliment miqdori', 'url' => 'https://lex.uz/docs/-104720#'.$art->anchor], $kb->citation($art));
        $this->assertSame('7¹-modda', $kb->citation($kb->article('advokatura', '7-1'))['article']);
    }

    public function test_keyword_search_handles_ascii_apostrophes(): void
    {
        $this->loadLaws(['advokatura', 'soliq-kodeksi']);
        $kb = app(LawKnowledgeBase::class);

        $this->assertSame('7-1', $kb->search("advokat so'rovi muddati", 3)->first()?->article_number);
        $this->assertSame('29', $kb->search('soliq siri', 3)->first()?->article_number);
    }

    public function test_semantic_search_uses_gemini_embeddings_with_cosine(): void
    {
        $this->loadLaws(['advokatura']);
        config(['lexlink.ai.gemini_key' => 'test-key']);
        $target = LawChunk::where('article_number', '9')->first();
        LawChunk::query()->update(['embedding' => json_encode([0.0, 1.0, 0.0])]);
        $target->forceFill(['embedding' => [1.0, 0.0, 0.0]])->save();

        Http::fake(['*:batchEmbedContents' => Http::response(['embeddings' => [['values' => [0.9, 0.1, 0.0]]]])]);

        $kb = app(LawKnowledgeBase::class);
        $kb->flushCache();
        $hits = $kb->search('sir saqlash', 2);

        $this->assertSame($target->id, $hits->first()->id);
        Http::assertSent(fn (Request $r) => $r['requests'][0]['taskType'] === 'RETRIEVAL_QUERY' && $r['requests'][0]['outputDimensionality'] === 768);
    }

    public function test_embed_command_skips_without_key_and_embeds_with_key(): void
    {
        $this->loadLaws(['advokatlik-kafolatlari']);
        config(['lexlink.ai.gemini_key' => null]);
        $this->artisan('lex:embed')->expectsOutputToContain("GEMINI_API_KEY yo'q")->assertSuccessful();

        config(['lexlink.ai.gemini_key' => 'test-key']);
        $count = LawChunk::count();
        Http::fake(['*:batchEmbedContents' => fn (Request $r) => Http::response(['embeddings' => array_map(fn () => ['values' => [3.0, 4.0]], $r['requests'])])]);
        $this->artisan('lex:embed')->assertSuccessful();

        $this->assertSame(0, LawChunk::whereNull('embedding')->count());
        $this->assertEqualsWithDelta([0.6, 0.8], LawChunk::first()->embedding, 1e-6);
        $this->assertGreaterThan(0, $count);
    }
}
