<?php

namespace App\Http\Controllers\Client;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\CaseClientLink;
use App\Models\LegalCase;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/** Read-only client pages. Every view is audited (ClientViewed). Clients never create requests. */
class ClientPortalController extends Controller
{
    public function index(Request $request, AuditLogger $audit): Response
    {
        $user = $request->user();
        $links = CaseClientLink::with('legalCase.owner.advocateProfile')
            ->where('client_user_id', $user->id)->whereNull('revoked_at')
            ->latest('id')->get()->unique('case_id')->filter(fn ($l) => $l->legalCase);

        $audit->log(AuditAction::ClientViewed, null, ['page' => 'index', 'cases' => $links->pluck('case_id')->values()->all()], user: $user);

        return Inertia::render('client/index', [
            'cases' => $links->map(fn (CaseClientLink $l) => ClientPresenter::legalCase($l->legalCase) + [
                'order_number' => $l->order_number,
                'requests_count' => $l->legalCase->dataRequests()->whereIn('status', array_map(fn ($s) => $s->value, ClientPresenter::VISIBLE))->count(),
                'granted_at' => $l->granted_at?->toIso8601String(),
            ])->values(),
            'note' => ClientPresenter::NOTE,
        ])->toResponse($request);
    }

    public function show(Request $request, LegalCase $legalCase, AuditLogger $audit): Response
    {
        $user = $request->user();
        $link = CaseClientLink::where('case_id', $legalCase->id)
            ->where('client_user_id', $user->id)->whereNull('revoked_at')
            ->latest('id')->first();

        if (! $link || ! $user->pinfl_hash || ! hash_equals($link->client_pinfl_hash, $user->pinfl_hash)) {
            $audit->log(AuditAction::ClientAccessDenied, $legalCase, ['reason' => 'no_link'], $legalCase->id, $user);

            return ClientAccessController::denied($request, 'no_link', "Ruxsat yo'q", "Bu ish bo'yicha sizga kirish huquqi berilmagan yoki advokat havolani bekor qilgan.");
        }

        $legalCase->load('owner.advocateProfile');
        $requests = $legalCase->dataRequests()->with('response')
            ->whereIn('status', array_map(fn ($s) => $s->value, ClientPresenter::VISIBLE))
            ->latest('id')->get();

        $link->forceFill(['last_viewed_at' => now()])->save();
        $audit->log(AuditAction::ClientViewed, $legalCase, [
            'page' => 'case',
            'order_number' => $link->order_number,
            'requests' => $requests->pluck('id')->all(),
        ], $legalCase->id, $user);

        return Inertia::render('client/case', [
            'case' => ClientPresenter::legalCase($legalCase) + ['order_number' => $link->order_number],
            'requests' => $requests->map(fn ($r) => ClientPresenter::request($r))->values(),
            'note' => ClientPresenter::NOTE,
        ])->toResponse($request);
    }
}
