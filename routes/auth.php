<?php

use App\Http\Controllers\Audit\AuditLogController;
use App\Http\Controllers\Auth\OnboardingController;
use App\Http\Controllers\Auth\OneIdController;
use App\Http\Controllers\Team\InviteController;
use App\Http\Controllers\Team\SecrecyDeclarationController;
use App\Http\Controllers\Team\TeamController;
use Illuminate\Support\Facades\Route;

// Owned by auth-team — OneID login, onboarding, team/invites, secrecy declaration, audit journal.

Route::middleware('guest')->group(function () {
    Route::get('login', [OneIdController::class, 'login'])->name('login');
    Route::get('auth/oneid/redirect', [OneIdController::class, 'redirect'])->name('oneid.redirect');
    Route::get('auth/oneid/callback', [OneIdController::class, 'callback'])->name('oneid.callback');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [OneIdController::class, 'logout'])->name('logout');

    Route::get('onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::get('onboarding/role', [OnboardingController::class, 'role'])->name('onboarding.role');
    Route::post('onboarding/role', [OnboardingController::class, 'storeRole'])->name('onboarding.role.store');
    Route::get('onboarding/license', [OnboardingController::class, 'license'])->name('onboarding.license');
    Route::post('onboarding/license', [OnboardingController::class, 'storeLicense'])
        ->middleware('throttle:10,1')->name('onboarding.license.store');

    // Invites work before onboarding: accepting one makes a fresh user a lawyer.
    Route::get('invites/{token}', [InviteController::class, 'show'])->name('invites.show');
    Route::post('invites/{token}/accept', [InviteController::class, 'accept'])->name('invites.accept');
});

Route::middleware(['auth', 'onboarded'])->group(function () {
    Route::get('team', [TeamController::class, 'index'])->middleware('role:advocate,lawyer')->name('team.index');
    Route::post('team/invites', [TeamController::class, 'store'])->middleware('role:advocate')->name('team.invites.store');
    Route::delete('team/links/{link}', [TeamController::class, 'destroy'])->middleware('role:advocate')->name('team.links.destroy');

    Route::post('cases/{case}/secrecy-declaration', [SecrecyDeclarationController::class, 'store'])
        ->whereNumber('case')->name('cases.secrecy-declaration.store');

    Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');
});
