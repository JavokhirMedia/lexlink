<?php

namespace App\Jobs;

use App\Enums\DataRequestStatus;
use App\Models\DataRequest;
use App\Models\RequestResponse;
use App\Services\DataRequestService;
use App\Services\Gov\GovApiException;
use App\Services\Gov\GovDataProvider;
use App\Services\Requests\ResponseSummarizer;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Polls the agency until the request is completed / rejected.
 * Not ready -> released back with a 2 s delay (up to MAX_POLLS). In `sync` mode it just returns;
 * the show page re-polls (see DataRequestController::show).
 */
class FetchGovResultJob implements ShouldQueue
{
    use Queueable;

    public const MAX_POLLS = 30;

    public int $tries = self::MAX_POLLS + 2;

    public function __construct(public int $dataRequestId) {}

    public static function isSync(?Job $job): bool
    {
        return $job === null || $job instanceof SyncJob || $job->getConnectionName() === 'sync';
    }

    public function handle(GovDataProvider $gov, DataRequestService $service, ResponseSummarizer $summarizer): void
    {
        $r = DataRequest::find($this->dataRequestId);
        if (! $r || $r->status->isFinal() || ! $r->gov_request_id) {
            return;
        }
        if ($r->status === DataRequestStatus::Submitted) {
            $r = $service->transition($r, DataRequestStatus::Processing);
        }

        try {
            $status = $gov->status($r->gov_request_id);
        } catch (GovApiException $e) {
            Log::warning("Gov status poll failed for request {$r->id}: ".$e->getMessage());
            $this->retryLater($r, $service);

            return;
        }

        match ($status['status'] ?? null) {
            'completed' => $this->complete($r, $gov, $service, $summarizer),
            'rejected' => $this->reject($r, $service, (string) ($status['rejection_reason'] ?? "Idora so'rovni rad etdi")),
            default => $this->retryLater($r, $service),
        };
    }

    private function complete(DataRequest $r, GovDataProvider $gov, DataRequestService $service, ResponseSummarizer $summarizer): void
    {
        try {
            $result = $gov->result($r->gov_request_id);
        } catch (GovApiException $e) {
            $this->retryLater($r, $service);

            return;
        }

        DB::transaction(function () use ($r, $result, $service, $summarizer) {
            $response = RequestResponse::updateOrCreate(['data_request_id' => $r->id], [
                'payload' => $result,
                'summary' => $summarizer->summarize($r->type, $result),
                'gov_signature' => $result['signature'] ?? null,
                'received_at' => now(),
                'expires_at' => now()->addDays((int) config('lexlink.response_retention_days', 30)),
                'purged_at' => null,
            ]);

            $service->transition($r, DataRequestStatus::Completed); // audits ResponseReceived
        });

        AnalyzeResponseJob::dispatch($r->id);
    }

    private function reject(DataRequest $r, DataRequestService $service, string $reason): void
    {
        $service->transition($r, DataRequestStatus::Rejected, ['rejection_reason' => mb_substr($reason, 0, 250)]);
        $service->refund($r);
    }

    private function retryLater(DataRequest $r, DataRequestService $service): void
    {
        if (self::isSync($this->job)) {
            return; // sync driver: never block; the next poll comes from the UI
        }

        if ($this->attempts() >= self::MAX_POLLS) {
            $service->transition($r, DataRequestStatus::Failed, [
                'failure_reason' => "Idora belgilangan vaqtda javob bermadi. Mablag' qaytarildi.",
            ]);
            $service->refund($r);

            return;
        }

        $this->release(2);
    }
}
