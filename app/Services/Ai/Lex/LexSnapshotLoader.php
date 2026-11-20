<?php

namespace App\Services\Ai\Lex;

use App\Models\LawChunk;
use App\Models\LawDocument;
use Illuminate\Support\Facades\DB;

/**
 * Loads the normalized lex.uz snapshot (storage/app/lex/laws.json) into law_documents / law_chunks.
 * Embeddings of chunks whose text did not change are preserved.
 */
class LexSnapshotLoader
{
    public const MAX_CHUNK_CHARS = 3500;

    /**
     * @param  array{documents: list<array<string, mixed>>}  $snapshot
     * @return array<string, int> slug => chunk count
     */
    public function load(array $snapshot, ?string $connection = null): array
    {
        $result = [];

        foreach ($snapshot['documents'] as $doc) {
            $result[$doc['slug']] = DB::connection($connection)->transaction(function () use ($doc, $connection) {
                $document = (new LawDocument)->setConnection($connection)->newQuery()->updateOrCreate(
                    ['slug' => $doc['slug']],
                    [
                        'title' => $doc['title'],
                        'short_title' => $doc['short_title'],
                        'lex_id' => $doc['lex_id'],
                        'lex_url' => $doc['lex_url'],
                        'adopted_at' => $doc['adopted_at'] ?? null,
                    ],
                );

                $existing = (new LawChunk)->setConnection($connection)->newQuery()
                    ->where('law_document_id', $document->id)
                    ->whereNotNull('embedding')
                    ->get(['content_hash', 'embedding'])
                    ->mapWithKeys(fn (LawChunk $c) => [$c->content_hash => $c->getRawOriginal('embedding')]);

                (new LawChunk)->setConnection($connection)->newQuery()->where('law_document_id', $document->id)->delete();

                $rows = [];
                $now = now();
                foreach ($doc['articles'] as $article) {
                    foreach ($this->chunk($article) as $i => $content) {
                        $hash = hash('sha256', $content);
                        $rows[] = [
                            'law_document_id' => $document->id,
                            'article_number' => $article['number'],
                            'article_title' => mb_substr((string) $article['title'], 0, 512),
                            'anchor' => $article['anchor'] ?? null,
                            'chapter' => isset($article['chapter']) ? mb_substr($article['chapter'], 0, 512) : null,
                            'part' => $i + 1,
                            'content' => $content,
                            'content_hash' => $hash,
                            'embedding' => $existing[$hash] ?? null,
                            'token_count' => (int) ceil(mb_strlen($content) / 4),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                foreach (array_chunk($rows, 200) as $batch) {
                    DB::connection($connection)->table('law_chunks')->insert($batch);
                }

                return count($rows);
            });
        }

        return $result;
    }

    /**
     * Split an article into chunks of at most MAX_CHUNK_CHARS on paragraph boundaries.
     * Every chunk starts with the article heading so it is self-contained for retrieval.
     *
     * @param  array{heading?: string, number: string, title: string, text: string}  $article
     * @return list<string>
     */
    public function chunk(array $article): array
    {
        $heading = $article['heading'] ?? "{$article['number']}-modda. {$article['title']}";
        $paragraphs = preg_split("/\n+/u", trim($article['text'])) ?: [];

        $chunks = [];
        $buffer = '';
        foreach ($paragraphs as $p) {
            if ($buffer !== '' && mb_strlen($buffer) + mb_strlen($p) > self::MAX_CHUNK_CHARS) {
                $chunks[] = $buffer;
                $buffer = '';
            }
            // a single over-long paragraph is hard-split
            while (mb_strlen($p) > self::MAX_CHUNK_CHARS) {
                $chunks[] = trim($buffer."\n".mb_substr($p, 0, self::MAX_CHUNK_CHARS));
                $buffer = '';
                $p = mb_substr($p, self::MAX_CHUNK_CHARS);
            }
            $buffer = $buffer === '' ? $p : $buffer."\n".$p;
        }
        if ($buffer !== '' || $chunks === []) {
            $chunks[] = $buffer;
        }

        return array_map(fn (string $c) => trim($heading."\n".$c), $chunks);
    }
}
