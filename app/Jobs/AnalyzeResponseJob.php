<?php

namespace App\Jobs;

use App\Enums\DataRequestStatus;
use App\Models\DataRequest;
use App\Services\Ai\AiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Runs the AI summary on a completed response. AI failures never affect the request itself. */
class AnalyzeResponseJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $dataRequestId) {}

    public function handle(): void
    {
        $r = DataRequest::find($this->dataRequestId);
        if (! $r || $r->status !== DataRequestStatus::Completed || ! app()->bound(AiService::class)) {
            return;
        }

        try {
            app(AiService::class)->analyzeResponse($r);
        } catch (Throwable $e) {
            Log::warning("AI analysis failed for request {$r->id}: ".$e->getMessage());
        }
    }
}
