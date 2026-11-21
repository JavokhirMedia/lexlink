<?php

namespace App\Services\Ai;

use App\Models\LawChunk;
use App\Services\Ai\Anonymization\AnonymizationContext;
use App\Services\Ai\Anonymization\AnonymizationGateway;
use App\Services\Ai\Anonymization\PiiLeakBlocked;
use App\Services\Ai\Lex\LexCatalog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * RAG over lex.uz articles stored in law_chunks.
 * Semantic search = Gemini query embedding + cosine (dot product of L2-normalised vectors) in PHP;
 * falls back to keyword (LIKE) scoring when there is no key, no embeddings, Gemini errors, or the
 * anonymisation leak guard blocks the query. The query embedding goes through AnonymizationGateway like any prompt.
 */
class LawKnowledgeBase
{
    /** @var array<int, string>|null chunk id => packed float32 vector */
    private ?array $vectors = null;

    private ?string $vectorsKey = null;

    private const STOPWORDS = ['va', 'yoki', 'bilan', 'uchun', 'bu', 'shu', 'qanday', 'qaysi', 'nima', 'necha', 'qancha', 'kerak', 'mumkin', 'emas', 'bor', 'yoʻq', 'ham', 'esa', 'agar', 'deb', 'oʻz', 'men', 'biz', 'ular', 'uni', 'bunda', 'haqida', 'boʻyicha', 'toʻgʻrisida', 'qilish', 'boʻladi', 'hisoblanadi', 'modda', 'moddasi', 'qonun', 'qonuni', 'kodeks', 'kodeksi'];

    public function __construct(private GeminiClient $gemini, private AnonymizationGateway $gateway) {}

    /**
     * @param  AnonymizationContext|null  $ctx  case / user context for the leak guard (null = context-free detectors only)
     * @return Collection<int, LawChunk>
     */
    public function search(string $query, int $k = 6, ?AnonymizationContext $ctx = null): Collection
    {
        $query = trim($query);
        if ($query === '') {
            return new Collection;
        }

        if ($this->gemini->enabled() && $this->hasEmbeddings()) {
            try {
                return $this->semanticSearch($query, $k, $ctx);
            } catch (PiiLeakBlocked) {
                // already audited + logged by the gateway; search locally instead
            } catch (Throwable $e) {
                Log::warning('LawKnowledgeBase semantic search failed, using keyword fallback', ['error' => $e->getMessage()]);
            }
        }

        return $this->keywordSearch($query, $k);
    }

    /** Exact article lookup, e.g. article('oila-kodeksi', '99'). */
    public function article(string $slug, string $number): ?LawChunk
    {
        return LawChunk::query()->with('document')
            ->whereHas('document', fn ($q) => $q->where('slug', $slug))
            ->where('article_number', $number)
            ->orderBy('part')
            ->first();
    }

    /**
     * @param  list<array{0: string, 1: string}>  $refs  [[slug, number], ...]
     * @return Collection<int, LawChunk>
     */
    public function articles(array $refs): Collection
    {
        return new Collection(array_values(array_filter(array_map(fn ($r) => $this->article($r[0], $r[1]), $refs))));
    }

    /**
     * @return array{law: string, article: ?string, title: ?string, url: ?string}
     */
    public function citation(LawChunk $chunk): array
    {
        $doc = $chunk->document;

        return [
            'law' => $doc?->short_title ?? 'lex.uz',
            'article' => $chunk->article_number ? self::articleLabel($chunk->article_number) : null,
            'title' => $chunk->article_title,
            'url' => LexCatalog::articleUrl($doc?->lex_url, $chunk->anchor),
        ];
    }

    /**
     * Unique citations (one per law+article) in the given order.
     *
     * @param  iterable<LawChunk>  $chunks
     * @return list<array{law: string, article: ?string, title: ?string, url: ?string}>
     */
    public function citations(iterable $chunks): array
    {
        $out = [];
        foreach ($chunks as $chunk) {
            $c = $this->citation($chunk);
            $out[$c['law'].'|'.$c['article']] ??= $c;
        }

        return array_values($out);
    }

    /**
     * Prompt context block: numbered sources the model must cite as [n].
     *
     * @param  iterable<LawChunk>  $chunks
     */
    public function context(iterable $chunks, int $maxChars = 2500): string
    {
        $lines = [];
        $i = 0;
        foreach ($chunks as $chunk) {
            $i++;
            $c = $this->citation($chunk);
            $text = mb_strlen($chunk->content) > $maxChars ? mb_substr($chunk->content, 0, $maxChars).' …' : $chunk->content;
            $lines[] = "[{$i}] {$c['law']}, {$c['article']} — {$c['title']}\n{$text}";
        }

        return implode("\n\n", $lines);
    }

    /** "7-1" => "7¹-modda", "99" => "99-modda". */
    public static function articleLabel(string $number): string
    {
        if (str_contains($number, '-')) {
            [$main, $sup] = explode('-', $number, 2);
            $number = $main.strtr($sup, ['0' => '⁰', '1' => '¹', '2' => '²', '3' => '³', '4' => '⁴', '5' => '⁵', '6' => '⁶', '7' => '⁷', '8' => '⁸', '9' => '⁹']);
        }

        return $number.'-modda';
    }

    public function hasEmbeddings(): bool
    {
        return LawChunk::query()->whereNotNull('embedding')->exists();
    }

    public function flushCache(): void
    {
        $this->vectors = null;
        Cache::forget($this->cacheKey());
    }

    /** @return Collection<int, LawChunk> */
    private function semanticSearch(string $query, int $k, ?AnonymizationContext $ctx): Collection
    {
        $payload = $this->gateway->queryEmbedding($query, $ctx ?? AnonymizationContext::public('query_embedding'));
        $q = Cache::remember('lex.q.'.sha1($this->gemini->embeddingModel().'|'.$payload->texts[0]), now()->addDay(), fn () => $this->gemini->embedQuery($payload));
        if ($q === []) {
            return $this->keywordSearch($query, $k);
        }
        $dims = count($q);

        $scores = [];
        foreach ($this->vectors() as $id => $packed) {
            $v = unpack('g*', $packed);
            if (count($v) !== $dims) {
                continue;
            }
            $dot = 0.0;
            $j = 0;
            foreach ($v as $x) {
                $dot += $x * $q[$j++];
            }
            $scores[$id] = $dot;
        }
        arsort($scores);
        $ids = array_slice(array_keys($scores), 0, $k);

        $chunks = LawChunk::query()->with('document')->whereIn('id', $ids)->get()->keyBy('id');

        return new Collection(array_values(array_filter(array_map(fn ($id) => $chunks[$id] ?? null, $ids))));
    }

    /** @return array<int, string> */
    private function vectors(): array
    {
        $key = $this->cacheKey();
        if ($this->vectors !== null && $this->vectorsKey === $key) {
            return $this->vectors;
        }
        $this->vectorsKey = $key;

        // cached as base64 (binary is unsafe in text-based cache stores such as the MySQL database driver)
        return $this->vectors = array_map('base64_decode', Cache::rememberForever($key, function () {
            $out = [];
            LawChunk::query()->whereNotNull('embedding')->select(['id', 'embedding'])
                ->chunkById(500, function ($rows) use (&$out) {
                    foreach ($rows as $row) {
                        $v = json_decode($row->getRawOriginal('embedding'), true);
                        if (is_array($v) && $v !== []) {
                            $out[$row->id] = base64_encode(pack('g*', ...$v));
                        }
                    }
                });

            return $out;
        }));
    }

    private function cacheKey(): string
    {
        $stamp = LawChunk::query()->whereNotNull('embedding')->selectRaw('count(*) as c, max(updated_at) as m, max(id) as i')->first();

        return 'lex.vectors.'.md5(json_encode([$stamp?->c, $stamp?->m, $stamp?->i]));
    }

    /** @return Collection<int, LawChunk> */
    public function keywordSearch(string $query, int $k = 6): Collection
    {
        $normalized = self::normalize($query);
        $articleRefs = [];
        if (preg_match_all('/(\d+)\s*-?\s*modda/u', $normalized, $m)) {
            $articleRefs = $m[1];
        }

        $terms = collect(preg_split('/[^\p{L}\p{N}\']+/u', $normalized) ?: [])
            ->filter(fn ($t) => mb_strlen($t) >= 3 && ! in_array($t, self::STOPWORDS, true) && ! ctype_digit($t))
            ->map(fn ($t) => self::stem($t))
            ->unique()->take(8)->values();

        // adjacent word pairs ("bir bola", "advokat so'rov") get a phrase bonus
        $words = array_values(array_filter(preg_split('/[^\p{L}\p{N}\']+/u', $normalized) ?: [], fn ($w) => $w !== ''));
        $bigrams = [];
        for ($i = 0; $i + 1 < count($words); $i++) {
            if (! in_array($words[$i], self::STOPWORDS, true) || ! in_array($words[$i + 1], self::STOPWORDS, true)) {
                $bigrams[] = $words[$i].' '.self::stem($words[$i + 1]);
            }
        }

        if ($terms->isEmpty() && $articleRefs === []) {
            return new Collection;
        }

        // very short stems ("ish") match half the corpus — use them for scoring only, not for candidate selection
        $selective = $terms->filter(fn ($t) => mb_strlen($t) >= 4);
        $likes = ($selective->isEmpty() ? $terms : $selective)->map(fn ($t) => '%'.str_replace("'", '_', $t).'%');
        // title hits first (cheap + most relevant), then body hits, so the candidate cap never drops exact titles
        $byTitle = LawChunk::query()->with('document')
            ->where(function ($q) use ($likes, $articleRefs) {
                foreach ($likes as $like) {
                    $q->orWhere('article_title', 'like', $like);
                }
                if ($articleRefs) {
                    $q->orWhereIn('article_number', $articleRefs);
                }
            })->limit(300)->get();
        $byBody = $likes->isEmpty() ? collect() : LawChunk::query()->with('document')
            ->whereNotIn('id', $byTitle->pluck('id'))
            ->where(function ($q) use ($likes) {
                foreach ($likes as $like) {
                    $q->orWhere('content', 'like', $like);
                }
            })->limit(600)->get();
        $candidates = $byTitle->concat($byBody);

        $texts = $candidates->mapWithKeys(fn (LawChunk $c) => [$c->id => [self::normalize((string) $c->article_title), self::normalize($c->content)]]);
        $n = max(1, $texts->count());
        // IDF over the candidate set: rare terms ("aliment") outweigh frequent ones ("bola")
        $idf = $terms->mapWithKeys(fn ($t) => [$t => log(1 + $n / max(1, $texts->filter(fn ($x) => str_contains($x[1], $t))->count()))]);

        $scored = $candidates->map(function (LawChunk $c) use ($terms, $articleRefs, $texts, $idf, $bigrams) {
            [$title, $body] = $texts[$c->id];
            $score = 0.0;
            $matched = 0;
            foreach ($terms as $t) {
                $inTitle = substr_count($title, $t);
                $inBody = substr_count($body, $t);
                $matched += ($inTitle + $inBody) > 0 ? 1 : 0;
                $score += ($inTitle * 4 + min($inBody, 6) + ($inBody > 0 ? 2 : 0)) * $idf[$t];
            }
            foreach ($bigrams as $bg) {
                $score += str_contains($body, $bg) ? 5 : 0;
            }
            // prefer chunks covering more of the query terms
            $score *= ($matched / max(1, $terms->count())) ** 2;
            if (in_array($c->article_number, $articleRefs, true)) {
                $score += 6;
            }
            $score -= ($c->part - 1) * 0.5;

            return [$c, $score / (1 + log(1 + mb_strlen($body) / 2000))];
        })->filter(fn ($p) => $p[1] > 0)->sortByDesc(fn ($p) => $p[1])->take($k)->map(fn ($p) => $p[0]);

        return new Collection($scored->values()->all());
    }

    /** lowercase + unify Uzbek apostrophes (ʻ ʼ ’ ‘ `) to ASCII ' */
    public static function normalize(string $s): string
    {
        return mb_strtolower(str_replace(['ʻ', 'ʼ', '’', '‘', '`', 'ʹ'], "'", $s));
    }

    /** Very light Uzbek suffix stripping so "alimentni", "alimentlar" match "aliment". */
    private static function stem(string $t): string
    {
        $suffixes = ['larning', 'larini', 'laridan', 'lariga', 'larda', 'lardan', 'larni', 'lari', 'lar', 'ning', 'dagi', 'idan', 'idagi', 'ini', 'ni', 'ga', 'da', 'dan', 'si', 'ning'];
        foreach ($suffixes as $s) {
            if (mb_strlen($t) - mb_strlen($s) >= 4 && str_ends_with($t, $s)) {
                return mb_substr($t, 0, mb_strlen($t) - mb_strlen($s));
            }
        }

        return $t;
    }
}
