<?php

namespace App\Console\Commands;

use App\Enums\AuditAction;
use App\Models\AiAnalysis;
use App\Models\RequestResponse;
use App\Services\AuditLogger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/** Rule #5: full payload + PDF are deleted after response_retention_days; summary + audit remain. */
#[Signature('lexlink:purge-responses')]
#[Description("Saqlash muddati tugagan idora javoblarining to'liq ma'lumotini o'chiradi")]
class PurgeExpiredResponses extends Command
{
    public function handle(AuditLogger $audit): int
    {
        $count = 0;

        RequestResponse::query()
            ->whereNull('purged_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->with('dataRequest')
            ->chunkById(100, function ($responses) use ($audit, &$count) {
                foreach ($responses as $resp) {
                    if ($resp->pdf_path) {
                        Storage::disk('local')->delete($resp->pdf_path);
                    }
                    $resp->forceFill(['payload' => null, 'pdf_path' => null, 'purged_at' => now()])->save();

                    // AI analyses of this response keep only the masked text: drop the placeholder->real-value map.
                    AiAnalysis::where('data_request_id', $resp->data_request_id)->get()
                        ->each(function ($a) {
                            $s = (array) ($a->structured ?? []);
                            if (array_key_exists('_pii', $s)) {
                                unset($s['_pii']);
                                $a->forceFill(['structured' => $s])->save();
                            }
                        });

                    $audit->log(AuditAction::ResponsePurged, $resp, [
                        'data_request_id' => $resp->data_request_id,
                        'expired_at' => $resp->expires_at?->toIso8601String(),
                    ], $resp->dataRequest?->case_id, null);
                    $count++;
                }
            });

        $this->info("O'chirildi: {$count} ta javob");

        return self::SUCCESS;
    }
}
