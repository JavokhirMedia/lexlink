<?php

use App\Http\Controllers\Client\ClientAccessController;
use App\Http\Controllers\Client\ClientPortalController;
use Illuminate\Support\Facades\Route;

// Client (ishonch bildiruvchi) read-only access via the advocate order. Owned by order-client agent.

// Works before onboarding: a brand-new OneID user becomes role `client` only through this link.
Route::middleware('auth')->get('client/access/{token}', ClientAccessController::class)
    ->where('token', '[A-Za-z0-9\-]{16,64}')->name('client.access');

Route::middleware(['auth', 'onboarded', 'role:client'])->group(function () {
    Route::get('client', [ClientPortalController::class, 'index'])->name('client.index');
    Route::get('client/cases/{legalCase}', [ClientPortalController::class, 'show'])->whereNumber('legalCase')->name('client.cases.show');
});
