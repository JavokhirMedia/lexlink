<?php

namespace App\Providers;

use App\Services\Ai\AiService;
use App\Services\Ai\Anonymization\AnonymizationGateway;
use App\Services\Ai\GeminiAiService;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\LawKnowledgeBase;
use App\Services\Ai\PromptRepository;
use Illuminate\Support\ServiceProvider;

/** ai module: AiService => GeminiAiService (falls back to deterministic templates without a key / on errors). */
class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GeminiClient::class);
        $this->app->singleton(AnonymizationGateway::class);
        $this->app->singleton(PromptRepository::class);
        $this->app->singleton(LawKnowledgeBase::class);
        $this->app->bind(AiService::class, GeminiAiService::class);
    }
}
