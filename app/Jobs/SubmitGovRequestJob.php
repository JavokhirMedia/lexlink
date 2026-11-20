<?php

namespace App\Jobs;

use App\Enums\DataRequestStatus;
use App\Models\DataRequest;
use App\Services\DataRequestService;
use App\Services\Gov\Dto\GovSubmission;
use App\Services\Gov\GovApiException;
use App\Services\Gov\GovDataProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends a signed request to the agency. Transport errors are retried 3x; after that the
 * request becomes `failed` and the charge is refunded. In `sync` mode retries run inline, no delays.
 */
class SubmitGovRequestJob implements ShouldQueue
{
    use Queueable;

    public const MAX_ATTEMPTS = 3;

    public int $tries = self::MAX_ATTEMPTS;

    public function __construct(public int $dataRequestId) {}

    public function backoff(): array
    {
        return [2, 5];
    }

    public function handle(GovDataProvider $gov, DataRequestService $service): void
    {
        $r = DataRequest::with(['legalBasis', 'signer.advocateProfile', 'legalCase'])->find($this->dataRequestId);
        if (! $r || $r->status !== DataRequestStatus::Submitted) {
            return;
        }

        $submission = $this->submission($r);
        $inline = FetchGovResultJob::isSync($this->job);
        $error = null;

        for ($i = 0; $i < ($inline ? self::MAX_ATTEMPTS : 1); $i++) {
            try {
                $response = $gov->submit($submission);
                $r->forceFill(['gov_request_id' => (string) $response['request_id']])->save();
                $service->transition($r, DataRequestStatus::Processing);

                FetchGovResultJob::dispatch($r->id)->delay(now()->addSeconds(2));

                return;
            } catch (GovApiException $e) {
                $error = $e;
                Log::warning("Gov submit failed for request {$r->id}: ".$e->getMessage());
            }
        }

        if (! $inline && $this->attempts() < self::MAX_ATTEMPTS) {
            $this->release($this->backoff()[$this->attempts() - 1] ?? 5);

            return;
        }

        $this->markFailed($r, $service, $error);
    }

    public function failed(?Throwable $e): void
    {
        $r = DataRequest::find($this->dataRequestId);
        if ($r && ! $r->status->isFinal()) {
            $this->markFailed($r, app(DataRequestService::class), $e);
        }
    }

    private function markFailed(DataRequest $r, DataRequestService $service, ?Throwable $e): void
    {
        $service->transition($r, DataRequestStatus::Failed, [
            'failure_reason' => "Idora tizimiga ulanib bo'lmadi (".self::MAX_ATTEMPTS." urinish). Mablag' qaytarildi.",
        ]);
        $service->refund($r);
    }

    private function submission(DataRequest $r): GovSubmission
    {
        $params = $r->params ?? [];
        $period = isset($params['period_from']) || isset($params['period_to'])
            ? array_filter(['from' => $params['period_from'] ?? null, 'to' => $params['period_to'] ?? null])
            : null;

        return new GovSubmission(
            type: $r->type->value,
            subjectPinfl: (string) $r->subject_pinfl,
            legalBasis: ['type' => $r->legalBasis->type->value, 'number' => $r->legalBasis->number],
            period: $period,
            requesterLicense: (string) $r->signer?->advocateProfile?->license_number,
            params: array_diff_key($params, array_flip(['period_from', 'period_to'])),
        );
    }
}
