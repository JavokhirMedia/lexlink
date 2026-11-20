<?php

namespace App\Services\Ai;

use App\Services\AuditLogger;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Gemini + lex.uz RAG. Every prompt is passed through the PiiMasker one more time right before
 * it leaves the server; on a missing key or any Gemini error the deterministic template is used.
 */
class GeminiAiService extends BaseAiService
{
    public function __construct(
        LawKnowledgeBase $kb,
        AlimonyCalculator $calculator,
        PromptRepository $prompts,
        AuditLogger $audit,
        private GeminiClient $gemini,
    ) {
        parent::__construct($kb, $calculator, $prompts, $audit);
    }

    protected function complete(string $prompt, string $fallback, PiiMasker $masker, array $options = []): array
    {
        if (! $this->gemini->enabled()) {
            return ['text' => $fallback, 'tokens' => 0, 'model' => FallbackAiService::MODEL];
        }

        try {
            $result = $this->gemini->generate($this->prompts->system(), $masker->mask($prompt), $options);

            return ['text' => $this->clean($result['text']), 'tokens' => $result['tokens'], 'model' => $result['model']];
        } catch (Throwable $e) {
            Log::warning('Gemini generation failed, using fallback template', ['error' => mb_substr($e->getMessage(), 0, 300)]);

            return ['text' => $fallback, 'tokens' => 0, 'model' => FallbackAiService::MODEL.' (gemini error)'];
        }
    }

    /** Strip a wrapping ```markdown fence if the model added one. */
    private function clean(string $text): string
    {
        return trim(preg_replace('/^```(?:markdown|md)?\s*\n(.*)\n```\s*$/s', '$1', trim($text)) ?? $text);
    }
}
