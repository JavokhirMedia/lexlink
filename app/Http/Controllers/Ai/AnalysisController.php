<?php

namespace App\Http\Controllers\Ai;

use App\Enums\DataRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\DataRequest;
use App\Services\Ai\AiAnalysisPresenter;
use App\Services\Ai\AiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Throwable;

class AnalysisController extends Controller
{
    /** "Qayta tahlil" — re-run the AI analysis of a completed response. */
    public function store(Request $request, DataRequest $dataRequest, AiService $ai): RedirectResponse|JsonResponse
    {
        Gate::authorize('viewResponses', $dataRequest->legalCase);

        if ($dataRequest->status !== DataRequestStatus::Completed || ! $dataRequest->response) {
            abort(422, "Tahlil faqat javob olingan so'rov uchun mumkin.");
        }

        try {
            $analysis = $ai->analyzeResponse($dataRequest);
        } catch (Throwable $e) {
            Log::error('AI analysis failed', ['request_id' => $dataRequest->id, 'error' => $e->getMessage()]);
            if ($request->wantsJson() && ! $request->header('X-Inertia')) {
                return response()->json(['message' => "Tahlil qilib bo'lmadi."], 500);
            }
            Inertia::flash('toast', ['type' => 'error', 'message' => "Tahlil qilib bo'lmadi. Keyinroq urinib ko'ring."]);

            return back();
        }

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['analysis' => AiAnalysisPresenter::present($analysis)]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'AI tahlil yangilandi.']);

        return back();
    }
}
