<?php

namespace App\Services\Ai;

use App\Models\AiAnalysis;

/**
 * Serialises an AiAnalysis for the UI (TS type AiAnalysis in @/types/lexlink):
 * placeholders ([SHAXS_1] ...) are restored locally from structured._pii; JShShIR stays masked.
 * Callers must have already authorised the viewer (LegalCasePolicy::viewResponses).
 */
class AiAnalysisPresenter
{
    public static function present(?AiAnalysis $a): ?array
    {
        if (! $a) {
            return null;
        }

        $structured = (array) ($a->structured ?? []);
        $map = (array) ($structured['_pii'] ?? []);
        unset($structured['_pii']);

        return [
            'id' => $a->id,
            'kind' => $a->kind,
            'status' => $a->status,
            'output' => $a->output !== null ? PiiMasker::restore($a->output, $map) : null,
            'structured' => self::restoreDeep($structured, $map),
            'citations' => $a->citations ?? [],
            'model' => $a->model,
            'prompt_version' => $a->prompt_version,
            'tokens' => (int) $a->tokens,
            'cost' => (int) $a->cost,
            'disclaimer' => $structured['disclaimer'] ?? (string) config('lexlink.ai.disclaimer'),
            'data_request_id' => $a->data_request_id,
            'case_id' => $a->case_id,
            'created_at' => $a->created_at?->toIso8601String(),
        ];
    }

    private static function restoreDeep(array $data, array $map): array
    {
        foreach ($data as $k => $v) {
            $data[$k] = is_array($v) ? self::restoreDeep($v, $map) : (is_string($v) ? PiiMasker::restore($v, $map) : $v);
        }

        return $data;
    }
}
