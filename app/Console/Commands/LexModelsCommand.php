<?php

namespace App\Console\Commands;

use App\Services\Ai\GeminiClient;
use Illuminate\Console\Command;

/** Lists Gemini models available for the configured key and suggests GEMINI_MODEL / GEMINI_EMBEDDING_MODEL. */
class LexModelsCommand extends Command
{
    protected $signature = 'lex:models';

    protected $description = 'List available Gemini models (ListModels) and suggest config defaults';

    public function handle(GeminiClient $gemini): int
    {
        if (! $gemini->enabled()) {
            $this->warn("GEMINI_API_KEY yo'q.");

            return self::FAILURE;
        }

        $models = collect($gemini->listModels());
        $gen = $models->filter(fn ($m) => in_array('generateContent', $m['methods'], true) && str_starts_with($m['name'], 'gemini-'))
            ->reject(fn ($m) => preg_match('/(image|tts|audio|live|embedding|robotics|computer-use)/', $m['name']));
        $emb = $models->filter(fn ($m) => in_array('embedContent', $m['methods'], true) || in_array('batchEmbedContents', $m['methods'], true));

        $this->table(['generateContent'], $gen->map(fn ($m) => [$m['name']])->all());
        $this->table(['embedContent'], $emb->map(fn ($m) => [$m['name']])->all());

        $rank = fn (string $n) => [
            (float) (preg_match('/gemini-(\d+(?:\.\d+)?)/', $n, $mm) ? $mm[1] : 0),
            str_contains($n, 'pro') ? 2 : (str_contains($n, 'flash') && ! str_contains($n, 'lite') ? 1 : 0),
            str_contains($n, 'preview') || str_contains($n, 'exp') ? 0 : 1,
        ];
        $best = $gen->sortByDesc(fn ($m) => $rank($m['name']))->first();
        $this->info('Tavsiya: GEMINI_MODEL='.($best['name'] ?? config('lexlink.ai.gemini_model'))
            .'  GEMINI_EMBEDDING_MODEL='.($emb->firstWhere('name', 'gemini-embedding-001')['name'] ?? $emb->first()['name'] ?? config('lexlink.ai.gemini_embedding_model')));

        return self::SUCCESS;
    }
}
