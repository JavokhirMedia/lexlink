<?php

namespace App\Http\Controllers;

use App\Enums\DataRequestStatus;
use App\Models\AdvocateLawyerLink;
use App\Models\CaseMember;
use App\Models\DataRequest;
use App\Models\LegalCase;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Spec: "faol ishlar, kutilayotgan imzolar, so'nggi javoblar, balans va oydagi so'rovlar soni".
 * Shows request metadata only (type / status / time) — never response payload.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, BillingService $billing): Response|RedirectResponse
    {
        $user = $request->user();
        if ($user->isClient()) {
            return to_route('client.index'); // clients (ishonch bildiruvchi) have their own read-only portal
        }
        $caseIds = $this->caseIdsFor($user);
        $usage = $billing->usage($user);

        $inFlight = [DataRequestStatus::Submitted->value, DataRequestStatus::Processing->value];
        $final = [DataRequestStatus::Completed->value, DataRequestStatus::Rejected->value, DataRequestStatus::Failed->value];

        $requests = fn () => DataRequest::with('legalCase:id,title')->whereIn('case_id', $caseIds);

        $activeCases = LegalCase::whereIn('id', $caseIds)->where('status', 'active')
            ->withCount([
                'dataRequests',
                'dataRequests as in_flight_count' => fn ($q) => $q->whereIn('status', $inFlight),
            ])
            ->latest('updated_at')->limit(6)->get()
            ->map(fn (LegalCase $c) => [
                'id' => $c->id,
                'title' => $c->title,
                'client_name' => $c->client_name,
                'court_case_number' => $c->court_case_number,
                'requests_count' => $c->data_requests_count,
                'in_flight_count' => $c->in_flight_count,
                'updated_at' => $c->updated_at?->toIso8601String(),
            ]);

        $props = [
            'role' => $user->role?->value,
            'kpi' => [
                'active_cases' => LegalCase::whereIn('id', $caseIds)->where('status', 'active')->count(),
                'awaiting_signature' => $requests()->where('status', DataRequestStatus::AwaitingSignature)->count(),
                'in_flight' => $requests()->whereIn('status', $inFlight)->count(),
                'used_requests' => $usage['used_requests'],
                'included_requests' => $usage['included_requests'],
                'plan_name' => $usage['plan']?->name,
                'balance' => (int) $billing->walletFor($user)->balance,
            ],
            'active_cases' => $activeCases,
            'recent' => $this->rows($requests()->whereIn('status', $final)->latest('updated_at')->limit(8)->get()),
            'in_flight' => $this->rows($requests()->whereIn('status', $inFlight)->latest('updated_at')->limit(5)->get()),
        ];

        if ($user->isAdvocate()) {
            $props['awaiting_signature'] = $this->rows(
                $requests()->with('creator:id,name')->where('status', DataRequestStatus::AwaitingSignature)->latest('updated_at')->limit(8)->get()
            );
        } else {
            $props['my_drafts'] = $this->rows(
                $requests()->where('created_by', $user->id)
                    ->whereIn('status', [DataRequestStatus::Draft->value, DataRequestStatus::AwaitingSignature->value])
                    ->latest('updated_at')->limit(8)->get()
            );
            $props['advocates'] = AdvocateLawyerLink::with('advocate:id,name', 'advocate.advocateProfile:id,user_id,license_number,license_status,bureau_name')
                ->where('lawyer_id', $user->id)->where('status', 'accepted')->get()
                ->map(fn (AdvocateLawyerLink $l) => [
                    'id' => $l->advocate?->id,
                    'name' => $l->advocate?->name,
                    'bureau_name' => $l->advocate?->advocateProfile?->bureau_name,
                    'license_status' => $l->advocate?->advocateProfile?->license_status,
                ])->filter(fn ($a) => $a['id'] !== null)->values();
        }

        return Inertia::render('dashboard', $props);
    }

    /** Cases the user owns or is a member of. */
    private function caseIdsFor(User $user): Collection
    {
        return LegalCase::where('owner_id', $user->id)->pluck('id')
            ->merge(CaseMember::where('user_id', $user->id)->pluck('case_id'))
            ->unique()->values();
    }

    private function rows(Collection $requests): Collection
    {
        return $requests->map(fn (DataRequest $r) => [
            'id' => $r->id,
            'case_id' => $r->case_id,
            'case_title' => $r->legalCase?->title,
            'type' => $r->type->value,
            'type_label' => $r->type->label(),
            'agency' => $r->type->agency(),
            'status' => $r->status->value,
            'status_label' => $r->status->label(),
            'subject_pinfl_masked' => $r->subject_pinfl_masked,
            'created_by_name' => $r->relationLoaded('creator') ? $r->creator?->name : null,
            'cost' => (int) $r->cost,
            'due_at' => $r->due_at?->toIso8601String(),
            'updated_at' => $r->updated_at?->toIso8601String(),
        ])->values();
    }
}
