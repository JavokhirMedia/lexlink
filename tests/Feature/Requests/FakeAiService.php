<?php

namespace Tests\Feature\Requests;

use App\Models\AiAnalysis;
use App\Models\DataRequest;
use App\Models\LegalCase;
use App\Services\Ai\AiService;

class FakeAiService implements AiService
{
    public int $analyzed = 0;

    public function draftRequestText(DataRequest $request): string
    {
        return 'AI matni: '.$request->type->label();
    }

    public function analyzeResponse(DataRequest $request): AiAnalysis
    {
        $this->analyzed++;

        return AiAnalysis::create([
            'data_request_id' => $request->id,
            'case_id' => $request->case_id,
            'kind' => 'summary',
            'status' => 'completed',
            'model' => 'fake',
            'output' => 'Tahlil',
        ]);
    }

    public function draftClaim(LegalCase $case, array $options = []): AiAnalysis
    {
        return new AiAnalysis;
    }

    public function askLaw(string $question): array
    {
        return ['answer' => '', 'citations' => []];
    }
}
