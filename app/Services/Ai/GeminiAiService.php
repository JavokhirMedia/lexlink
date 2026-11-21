<?php

namespace App\Services\Ai;

use App\Services\Ai\Anonymization\AnonymizationContext;
use App\Services\Ai\Anonymization\AnonymizationGateway;
use App\Services\Ai\Anonymization\AnonymizationReport;
use App\Services\Ai\Anonymization\PiiLeakBlocked;
use App\Services\Ai\Anonymization\ResponseProjector;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Gemini + lex.uz RAG. Every prompt goes through AnonymizationGateway (final masking pass + leak guard) —
 * GeminiClient accepts nothing else. On a missing key, a blocked payload or any Gemini error the deterministic
 * template is used.
 */
class GeminiAiService extends BaseAiService
{
    public function __construct(
        LawKnowledgeBase $kb,
        AlimonyCalculator $calculator,
        PromptRepository $prompts,
        AuditLogger $audit,
        AnonymizationGateway $gateway,
        ResponseProjector $projector,
        private GeminiClient $gemini,
    ) {
        parent::__construct($kb, $calculator, $prompts, $audit, $gateway, $projector);
    }

    protected function complete(string $prompt, string $fallback, AnonymizationContext $ctx, array $options = [], array $public = []): array
    {
        if (! $this->gemini->enabled()) {
            return ['text' => $fallback, 'tokens' => 0, 'model' => FallbackAiService::MODEL, 'sent' => null, 'report' => AnonymizationReport::notCalled($ctx)];
        }

        try {
            $payload = $this->gateway->generation($this->prompts->system(), $prompt, $ctx, $public);
        } catch (PiiLeakBlocked $e) {
            // audited (AiPiiBlocked) + logged by the gateway; nothing was sent
            return ['text' => $fallback, 'tokens' => 0, 'model' => FallbackAiService::MODEL.' (pii blocked)', 'sent' => null, 'report' => $e->report];
        }

        try {
            $result = $this->gemini->generate($payload, $options);

            return ['text' => $this->clean($result['text']), 'tokens' => $result['tokens'], 'model' => $result['model'], 'sent' => $payload->text, 'report' => $payload->report];
        } catch (Throwable $e) {
            Log::warning('Gemini generation failed, using fallback template', ['error' => mb_substr($e->getMessage(), 0, 300)]);

            return [
                'text' => $fallback, 'tokens' => 0, 'model' => FallbackAiService::MODEL.' (gemini error)',
                'sent' => $payload->text,
                'report' => ['note' => 'Gemini xatosi — matn yuborildi, lekin javob olinmadi; natija mahalliy shablon asosida.'] + $payload->report,
            ];
        }
    }

    /** Strip a wrapping ```markdown fence if the model added one. */
    private function clean(string $text): string
    {
        return trim(preg_replace('/^```(?:markdown|md)?\s*\n(.*)\n```\s*$/s', '$1', trim($text)) ?? $text);
    }
}
