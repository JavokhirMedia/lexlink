<?php

namespace App\Services\Ai;

use App\Services\Ai\Anonymization\AnonymizationContext;
use App\Services\Ai\Anonymization\AnonymizationReport;

/**
 * Deterministic AI service: templates + keyword search over lex.uz chunks.
 * Used when GEMINI_API_KEY is missing or Gemini fails, so the demo never breaks. Nothing leaves the server;
 * the anonymisation report says so ("AI chaqirilmadi").
 */
class FallbackAiService extends BaseAiService
{
    public const MODEL = 'fallback-template';

    protected function complete(string $prompt, string $fallback, AnonymizationContext $ctx, array $options = [], array $public = []): array
    {
        return ['text' => $fallback, 'tokens' => 0, 'model' => self::MODEL, 'sent' => null, 'report' => AnonymizationReport::notCalled($ctx)];
    }
}
