<?php

use App\Http\Controllers\Billing\BillingController;
use App\Http\Controllers\Billing\MockPaymentController;
use Illuminate\Support\Facades\Route;

// Owned by billing agent — see routes/web.php

Route::middleware(['auth', 'onboarded'])->prefix('billing')->name('billing.')->group(function () {
    Route::get('/', [BillingController::class, 'index'])->name('index');
    Route::post('plan', [BillingController::class, 'changePlan'])->name('plan');
    Route::get('checkout', [BillingController::class, 'checkout'])->name('checkout');

    // Demo payment gateway (Click / Payme shaped). Replace MockPaymentProvider to go live.
    Route::post('mock/{provider}/prepare', [MockPaymentController::class, 'prepare'])
        ->whereIn('provider', ['click', 'payme'])->middleware('throttle:20,1')->name('mock.prepare');
    Route::post('mock/{provider}/complete', [MockPaymentController::class, 'complete'])
        ->whereIn('provider', ['click', 'payme'])->middleware('throttle:20,1')->name('mock.complete');
});
