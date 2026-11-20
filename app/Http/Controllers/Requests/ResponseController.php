<?php

namespace App\Http\Controllers\Requests;

use App\Enums\AuditAction;
use App\Enums\DataRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\DataRequest;
use App\Models\RequestResponse;
use App\Models\User;
use App\Policies\DataRequestPolicy;
use App\Services\AuditLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Response access (rule #3 + #4): summary on the show page, full payload + PDF behind
 * LegalCasePolicy::viewResponses; every opening is audited. Admin: metadata only.
 */
class ResponseController extends Controller
{
    public const PURGED_NOTICE = "To'liq javob saqlash muddati (%d kun) tugagani sababli o'chirilgan";

    public function __construct(private AuditLogger $audit) {}

    /** Show-page prop. Logs ViewResponse when an authorised user sees the summary. */
    public static function present(DataRequest $r, User $user, bool $canView): ?array
    {
        $resp = $r->response;
        if (! $resp || $r->status !== DataRequestStatus::Completed) {
            return null;
        }

        $meta = [
            'received_at' => $resp->received_at?->toIso8601String(),
            'expires_at' => $resp->expires_at?->toIso8601String(),
            'purged' => $resp->isPurged(),
            'purged_at' => $resp->purged_at?->toIso8601String(),
            'purged_notice' => $resp->isPurged() ? sprintf(self::PURGED_NOTICE, (int) config('lexlink.response_retention_days', 30)) : null,
            'gov_signature' => $resp->gov_signature ? substr($resp->gov_signature, 0, 24).'…' : null,
        ];

        if (! $canView) {
            return $meta + ['summary' => null, 'restricted' => true];
        }

        app(AuditLogger::class)->log(AuditAction::ViewResponse, $r, ['response_id' => $resp->id], $r->case_id, $user);

        return $meta + ['summary' => $resp->summary, 'restricted' => false];
    }

    /** Full payload (JSON) for the table / raw view. */
    public function payload(Request $request, DataRequest $dataRequest): JsonResponse
    {
        $resp = $this->authorizedResponse($request, $dataRequest);

        $this->audit->log(AuditAction::ViewPayload, $dataRequest, ['response_id' => $resp->id], $dataRequest->case_id, $request->user());

        return response()->json([
            'type' => $dataRequest->type->value,
            'payload' => $resp->payload,
        ]);
    }

    public function pdf(Request $request, DataRequest $dataRequest): HttpResponse
    {
        $resp = $this->authorizedResponse($request, $dataRequest);
        $user = $request->user();
        $dataRequest->load(['legalCase', 'legalBasis', 'signer.advocateProfile']);

        $advocate = $dataRequest->signer ?? $dataRequest->legalCase->owner;
        $now = now()->setTimezone('Asia/Tashkent');
        $watermark = [
            'advocate' => $advocate?->name,
            'license' => $advocate?->advocateProfile?->license_number,
            'downloaded_by' => $user->name,
            'at' => $now->format('d.m.Y H:i'),
            'request_id' => $dataRequest->id,
            'gov_request_id' => $dataRequest->gov_request_id,
        ];

        $pdf = Pdf::loadView('pdf.response', [
            'r' => $dataRequest,
            'response' => $resp,
            'payload' => $resp->payload ?? [],
            'data' => $resp->payload['data'] ?? [],
            'summary' => $resp->summary ?? [],
            'watermark' => $watermark,
            'generatedAt' => $now,
        ])->setPaper('a4')->setOption(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false]);

        $content = $pdf->output();
        $path = "responses/request-{$dataRequest->id}.pdf";
        Storage::disk('local')->put($path, $content);
        if ($resp->pdf_path !== $path) {
            $resp->forceFill(['pdf_path' => $path])->save();
        }

        $this->audit->log(AuditAction::DownloadPdf, $dataRequest, [
            'response_id' => $resp->id,
            'watermark' => $watermark['downloaded_by'].' · '.$watermark['at'],
        ], $dataRequest->case_id, $user);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="lexlink-javob-'.$dataRequest->id.'.pdf"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private function authorizedResponse(Request $request, DataRequest $r): RequestResponse
    {
        Gate::authorize('view', $r);
        abort_unless(DataRequestPolicy::canViewResponses($request->user(), $r->legalCase), 403,
            DataRequestPolicy::needsSecrecyDeclaration($request->user(), $r->legalCase)
                ? "Javobni ko'rish uchun avval advokat sirini saqlash majburiyatini qabul qiling"
                : "Sizda ushbu ish bo'yicha javoblarni ko'rish huquqi yo'q");

        $resp = $r->response;
        abort_if(! $resp || $r->status !== DataRequestStatus::Completed, 404, 'Javob hali olinmagan');
        abort_if($resp->isPurged() || $resp->payload === null, 410, sprintf(self::PURGED_NOTICE, (int) config('lexlink.response_retention_days', 30)));

        return $resp;
    }
}
