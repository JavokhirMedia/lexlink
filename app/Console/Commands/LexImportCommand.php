<?php

namespace App\Console\Commands;

use App\Services\Ai\LawKnowledgeBase;
use App\Services\Ai\Lex\LexCatalog;
use App\Services\Ai\Lex\LexParser;
use App\Services\Ai\Lex\LexSnapshotLoader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Imports official law texts from lex.uz into the RAG knowledge base.
 *
 *   php artisan lex:import                  # fetch (raw HTML cached in storage/app/lex/raw), parse, write laws.json, load DB
 *   php artisan lex:import --refresh        # re-download even if cached
 *   php artisan lex:import --from-snapshot  # offline: load storage/app/lex/laws.json only (production)
 *   php artisan lex:import --no-db          # only (re)build the snapshot
 */
class LexImportCommand extends Command
{
    protected $signature = 'lex:import
        {--from-snapshot : Load the committed storage/app/lex/laws.json without contacting lex.uz}
        {--refresh : Re-download raw HTML even if cached}
        {--no-db : Only build the snapshot, do not write the database}
        {--only=* : Limit to catalog slugs}
        {--database= : Database connection to load into (default connection if omitted)}';

    protected $description = 'Import laws from lex.uz (Uzbek Latin) into law_documents / law_chunks';

    private const USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';

    public function handle(LexParser $parser, LexSnapshotLoader $loader): int
    {
        $connection = $this->option('database') ?: null;

        if ($this->option('from-snapshot')) {
            $path = LexCatalog::snapshotPath();
            if (! File::exists($path)) {
                $this->error("Snapshot topilmadi: {$path}");

                return self::FAILURE;
            }
            $snapshot = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);
            $snapshot['documents'] = $this->filterOnly($snapshot['documents']);

            return $this->loadIntoDb($loader, $snapshot, $connection);
        }

        File::ensureDirectoryExists(storage_path('app/lex/raw'));
        $documents = [];
        $failed = [];
        $fetched = 0;

        foreach ($this->filterOnly(LexCatalog::documents()) as $entry) {
            try {
                $html = $this->rawHtml($entry['lex_id'], $fetched);
            } catch (Throwable $e) {
                $failed[] = $entry['slug'];
                $this->error("{$entry['slug']}: lex.uz dan olib bo'lmadi — {$e->getMessage()}");

                continue;
            }

            $parsed = $parser->parse($html);
            $articles = $parsed['articles'];
            if (! empty($entry['only'])) {
                $articles = array_values(array_filter($articles, fn ($a) => in_array($a['number'], $entry['only'], true)));
            }

            if ($articles === []) {
                $failed[] = $entry['slug'];
                $this->error("{$entry['slug']}: moddalar topilmadi (sahifa tuzilishi o'zgargan bo'lishi mumkin).");

                continue;
            }

            $documents[] = [
                'slug' => $entry['slug'],
                'title' => $entry['title'],
                'official_title' => $parsed['title'],
                'short_title' => $entry['short_title'],
                'number' => $parsed['number'],
                'lex_id' => $entry['lex_id'],
                'lex_url' => LexCatalog::docUrl($entry['lex_id']),
                'language' => 'uz-Latn',
                'adopted_at' => $parsed['adopted_at'],
                'articles' => $articles,
            ];
            $this->line(sprintf('  %-32s %-10s %4d modda', $entry['slug'], $entry['lex_id'], count($articles)));
        }

        if ($documents === []) {
            $this->error("Hech qanday hujjat import qilinmadi. lex.uz mavjud emas — matn o'ylab topilmaydi.");

            return self::FAILURE;
        }

        // merge with an existing snapshot so --only does not drop other laws
        $existing = File::exists(LexCatalog::snapshotPath())
            ? collect(json_decode(File::get(LexCatalog::snapshotPath()), true)['documents'] ?? [])->keyBy('slug')
            : collect();
        foreach ($documents as $doc) {
            $existing[$doc['slug']] = $doc;
        }
        $order = array_column(LexCatalog::documents(), 'slug');
        $all = $existing->sortBy(fn ($d) => array_search($d['slug'], $order, true))->values()->all();

        $snapshot = [
            'source' => 'lex.uz — Oʻzbekiston Respublikasi Qonunchilik maʼlumotlari milliy bazasi',
            'generated_at' => now()->toIso8601String(),
            'documents' => $all,
        ];
        File::put(LexCatalog::snapshotPath(), json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        $this->info('Snapshot yozildi: '.LexCatalog::snapshotPath());

        if ($failed !== []) {
            $this->warn('Import qilinmagan: '.implode(', ', $failed));
        }

        if ($this->option('no-db')) {
            return $failed === [] ? self::SUCCESS : self::FAILURE;
        }

        $snapshot['documents'] = $documents;

        return $this->loadIntoDb($loader, $snapshot, $connection) === self::SUCCESS && $failed === [] ? self::SUCCESS : self::FAILURE;
    }

    private function loadIntoDb(LexSnapshotLoader $loader, array $snapshot, ?string $connection): int
    {
        $counts = $loader->load($snapshot, $connection);
        foreach ($counts as $slug => $n) {
            $this->line(sprintf('  DB  %-32s %5d chunk', $slug, $n));
        }
        app(LawKnowledgeBase::class)->flushCache();
        $this->info('Bilimlar bazasi yangilandi. Embedding uchun: php artisan lex:embed');

        return self::SUCCESS;
    }

    private function rawHtml(string $lexId, int &$fetched): string
    {
        $path = LexCatalog::rawPath($lexId);
        if (! $this->option('refresh') && File::exists($path) && File::size($path) > 10_000) {
            return File::get($path);
        }

        if ($fetched++ > 0) {
            usleep(1_500_000); // be polite to lex.uz
        }

        $response = Http::withHeaders(['User-Agent' => self::USER_AGENT, 'Accept-Language' => 'uz,ru;q=0.8'])
            ->timeout(60)->retry(2, 2000)
            ->get(LexCatalog::docUrl($lexId));

        $html = $response->throw()->body();
        if (! str_contains($html, 'divCont')) {
            throw new \RuntimeException('kutilmagan sahifa (divCont yo\'q)');
        }
        File::put($path, $html);

        return $html;
    }

    private function filterOnly(array $docs): array
    {
        $only = $this->option('only');

        return $only ? array_values(array_filter($docs, fn ($d) => in_array($d['slug'], $only, true))) : $docs;
    }
}
