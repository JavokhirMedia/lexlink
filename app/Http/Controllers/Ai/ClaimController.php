<?php

namespace App\Http\Controllers\Ai;

use App\Enums\AuditAction;
use App\Enums\DataRequestStatus;
use App\Enums\DataRequestType;
use App\Exceptions\InsufficientBalance;
use App\Http\Controllers\Controller;
use App\Models\AiAnalysis;
use App\Models\DataRequest;
use App\Models\LegalCase;
use App\Services\Ai\AiAnalysisPresenter;
use App\Services\Ai\AiService;
use App\Services\Ai\AlimonyCalculator;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ClaimController extends Controller
{
    public function show(Request $request, LegalCase $legalCase, AlimonyCalculator $calculator, AuditLogger $audit): Response
    {
        Gate::authorize('viewResponses', $legalCase);
        $legalCase->load(['dataRequests.response', 'owner']);

        $completed = $legalCase->dataRequests
            ->filter(fn (DataRequest $r) => $r->status === DataRequestStatus::Completed && $r->response)
            ->sortByDesc('completed_at')->values();
        $salary = $completed->first(fn (DataRequest $r) => $r->type === DataRequestType::Salary);
        $children = AlimonyCalculator::detectChildren($salary?->params ?? [], $legalCase->description, $legalCase->title);

        $draft = AiAnalysis::query()->where('case_id', $legalCase->id)->where('kind', 'draft_claim')->latest('id')->first();

        if ($draft) {
            $audit->log(AuditAction::ViewResponse, $draft, ['kind' => 'draft_claim'], $legalCase->id);
        }

        $preview = null;
        if ($salary) {
            $payload = $salary->response->isPurged() ? (array) $salary->response->summary : (array) $salary->response->payload;
            $preview = $calculator->calculate(($payload['data'] ?? $payload)['months'] ?? [], $children);
        }

        return Inertia::render('ai/claim', [
            'case' => [
                'id' => $legalCase->id,
                'title' => $legalCase->title,
                'court_case_number' => $legalCase->court_case_number,
                'client_name' => $legalCase->client_name,
                'category' => $legalCase->category,
            ],
            'responses' => $completed->map(fn (DataRequest $r) => [
                'id' => $r->id,
                'type' => $r->type->value,
                'type_label' => $r->type->label(),
                'agency' => $r->type->agency(),
                'completed_at' => $r->completed_at?->toIso8601String(),
            ])->values(),
            'has_salary' => (bool) $salary,
            'children_detected' => $children,
            'alimony_preview' => $preview,
            'draft' => AiAnalysisPresenter::present($draft),
            'price' => (int) config('lexlink.prices.ai_extra'),
        ]);
    }

    public function store(Request $request, LegalCase $legalCase, AiService $ai): RedirectResponse
    {
        Gate::authorize('viewResponses', $legalCase);
        $data = $request->validate([
            'children' => ['nullable', 'integer', 'min:1', 'max:15'],
            'court' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $ai->draftClaim($legalCase, array_filter([
                'user' => $request->user(),
                'children' => $data['children'] ?? null,
                'court' => $data['court'] ?? null,
            ]));
        } catch (InsufficientBalance $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage() ?: "Balans yetarli emas — balansni to'ldiring"]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Da'vo arizasi loyihasi tayyorlandi"]);

        return to_route('ai.claim.show', $legalCase);
    }
}
