<?php

use App\Http\Controllers\Ai\AnalysisController;
use App\Http\Controllers\Ai\AssistantController;
use App\Http\Controllers\Ai\ClaimController;
use Illuminate\Support\Facades\Route;

// Owned by the ai module agent — see routes/web.php

Route::middleware(['auth', 'onboarded', 'role:advocate,lawyer'])->group(function () {
    // Huquqiy yordamchi — law Q&A over the lex.uz knowledge base
    Route::get('ai/assistant', [AssistantController::class, 'show'])->name('ai.assistant');
    Route::post('ai/assistant/ask', [AssistantController::class, 'ask'])->middleware('throttle:20,1')->name('ai.assistant.ask');

    // Re-run AI analysis of a completed response ("Qayta tahlil")
    Route::post('ai/requests/{dataRequest}/analyze', [AnalysisController::class, 'store'])
        ->whereNumber('dataRequest')->middleware('throttle:10,1')->name('ai.requests.analyze');

    // Da'vo arizasi (aliment) draft for a case
    Route::get('cases/{legalCase}/claim', [ClaimController::class, 'show'])->whereNumber('legalCase')->name('ai.claim.show');
    Route::post('cases/{legalCase}/claim', [ClaimController::class, 'store'])->whereNumber('legalCase')->middleware('throttle:10,1')->name('ai.claim.store');
});
