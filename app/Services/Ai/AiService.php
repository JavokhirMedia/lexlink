<?php

namespace App\Services\Ai;

use App\Models\AiAnalysis;
use App\Models\DataRequest;
use App\Models\LegalCase;

/**
 * AI assistant. Implementation: GeminiAiService (Gemini + lex.uz RAG).
 * RULES (legal): only egasizlantirilgan data leaves the server (ShMQ 16-modda) — via
 * Anonymization\AnonymizationGateway, the only path to Gemini; such data is no longer personal data, so
 * transchegaraviy uzatish (ShMQ 15-modda) is not triggered. Every output is labelled "maslahat emas, yordamchi tahlil";
 * numbers (averages, alimony) are computed in PHP, never by the model.
 */
interface AiService
{
    /** Draft the formal request letter text for a data request (uz-Latn). Returns markdown/plain text. */
    public function draftRequestText(DataRequest $request): string;

    /** Analyse a completed response; persists and returns an AiAnalysis (kind=summary) with citations. */
    public function analyzeResponse(DataRequest $request): AiAnalysis;

    /** Draft a claim (da'vo arizasi) for a case using its completed responses; kind=draft_claim. */
    public function draftClaim(LegalCase $case, array $options = []): AiAnalysis;

    /** Free-form legal question against the lex.uz knowledge base; returns {answer, citations[]} */
    public function askLaw(string $question): array;
}
