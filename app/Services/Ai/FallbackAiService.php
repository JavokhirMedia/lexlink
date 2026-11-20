<?php

namespace App\Services\Ai;

/**
 * Deterministic AI service: templates + keyword search over lex.uz chunks.
 * Used when GEMINI_API_KEY is missing or Gemini fails, so the demo never breaks.
 */
class FallbackAiService extends BaseAiService
{
    public const MODEL = 'fallback-template';

    protected function complete(string $prompt, string $fallback, PiiMasker $masker, array $options = []): array
    {
        return ['text' => $fallback, 'tokens' => 0, 'model' => self::MODEL];
    }
}
