<?php

use App\Http\Controllers\MockGov\OneIdController;
use App\Http\Controllers\MockGov\RegistryController;
use App\Http\Controllers\MockGov\RequestController;
use App\Http\Controllers\MockGov\RequireMockGovApiKey;
use Illuminate\Support\Facades\Route;

// /mock-gov/v1/* — fictional government APIs. Owned by mock-gov agent. See docs/mock-gov-api.md.

// OneID (OAuth2). authorize is a browser page; token/userinfo are machine endpoints (client creds / Bearer).
Route::middleware('web')->get('oneid/authorize', [OneIdController::class, 'authorizeRequest'])->name('oneid.authorize');
Route::post('oneid/token', [OneIdController::class, 'token'])->name('oneid.token');
Route::get('oneid/userinfo', [OneIdController::class, 'userinfo'])->name('oneid.userinfo');

Route::get('health', fn () => response()->json([
    'status' => 'ok',
    'service' => 'mock-gov',
    'time' => now()->setTimezone('Asia/Tashkent')->toIso8601String(),
]))->name('health');

// Agency APIs — X-Api-Key required, JSON only, no session/CSRF.
Route::middleware(RequireMockGovApiKey::class)->group(function () {
    Route::get('advocates/{license}', [RegistryController::class, 'advocate'])->name('advocates.show');
    Route::get('court/decisions/{number}', [RegistryController::class, 'decision'])->where('number', '.+')->name('court.decisions.show');
    Route::get('court/cases/{number}', [RegistryController::class, 'courtCase'])->where('number', '.+')->name('court.cases.show');
    Route::post('requests', [RequestController::class, 'store'])->name('requests.store');
    Route::get('requests/{id}', [RequestController::class, 'show'])->name('requests.show');
});
