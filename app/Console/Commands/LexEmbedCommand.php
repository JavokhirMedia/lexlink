<?php

namespace App\Console\Commands;

use App\Models\LawChunk;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\LawKnowledgeBase;
use Illuminate\Console\Command;
use Throwable;

/**
 * Builds Gemini embeddings for law_chunks in batches (skips when GEMINI_API_KEY is missing).
 *   php artisan lex:embed            # only chunks without an embedding
 *   php artisan lex:embed --all      # re-embed everything (e.g. after changing the embedding model)
 */
class LexEmbedCommand extends Command
{
    protected $signature = 'lex:embed {--all : Re-embed all chunks} {--batch=50 : Chunks per batchEmbedContents call}';

    protected $description = 'Embed lex.uz law chunks with Gemini embeddings (RAG)';

    public function handle(GeminiClient $gemini, LawKnowledgeBase $kb): int
    {
        if (! $gemini->enabled()) {
            $this->warn("GEMINI_API_KEY yo'q — embedding o'tkazib yuborildi. Qidiruv kalit so'zlar bo'yicha ishlaydi.");

            return self::SUCCESS;
        }

        $query = LawChunk::query()->with('document');
        if (! $this->option('all')) {
            $query->whereNull('embedding');
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('Barcha chunklar embedding qilingan.');

            return self::SUCCESS;
        }

        $this->info("Embedding: {$total} chunk, model {$gemini->embeddingModel()}");
        $bar = $this->output->createProgressBar($total);
        $batch = max(1, min(100, (int) $this->option('batch')));
        $failed = 0;

        $query->chunkById($batch, function ($chunks) use ($gemini, $bar, &$failed) {
            $texts = $chunks->map(fn (LawChunk $c) => ($c->document?->title ?? '')."\n".$c->content)->all();
            try {
                $vectors = $gemini->embed(array_values($texts));
            } catch (Throwable $e) {
                $failed += $chunks->count();
                $this->newLine();
                $this->error('Batch xatosi: '.$e->getMessage());
                $bar->advance($chunks->count());

                return;
            }
            foreach ($chunks->values() as $i => $chunk) {
                if (isset($vectors[$i])) {
                    $chunk->forceFill(['embedding' => array_map(fn ($x) => round($x, 6), $vectors[$i])])->save();
                }
            }
            $bar->advance($chunks->count());
            usleep(200_000);
        });

        $bar->finish();
        $this->newLine();
        $kb->flushCache();

        if ($failed > 0) {
            $this->warn("{$failed} chunk embedding qilinmadi — qayta ishga tushiring.");

            return self::FAILURE;
        }
        $this->info('Tayyor.');

        return self::SUCCESS;
    }
}
