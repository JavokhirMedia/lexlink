<?php

use App\Http\Controllers\Cases\CaseController;
use App\Http\Controllers\Cases\CaseMemberController;
use App\Http\Controllers\Cases\LegalBasisController;
use Illuminate\Support\Facades\Route;

// Owned by the cases module agent — see routes/web.php

Route::middleware(['auth', 'onboarded', 'role:advocate,lawyer'])->group(function () {
    Route::get('cases', [CaseController::class, 'index'])->name('cases.index');
    Route::get('cases/create', [CaseController::class, 'create'])->name('cases.create');
    Route::post('cases', [CaseController::class, 'store'])->name('cases.store');
    Route::get('cases/{legalCase}', [CaseController::class, 'show'])->whereNumber('legalCase')->name('cases.show');

    // Legal bases (court decision / advocate order)
    Route::post('cases/{legalCase}/bases', [LegalBasisController::class, 'store'])->name('cases.bases.store');
    Route::post('cases/{legalCase}/bases/{basis}/verify', [LegalBasisController::class, 'verify'])->name('cases.bases.verify');
    Route::get('cases/{legalCase}/bases/{basis}/file', [LegalBasisController::class, 'file'])->name('cases.bases.file');

    // Team on a case + per-member permissions
    Route::post('cases/{legalCase}/members', [CaseMemberController::class, 'store'])->name('cases.members.store');
    Route::patch('cases/{legalCase}/members/{member}', [CaseMemberController::class, 'update'])->name('cases.members.update');
    Route::delete('cases/{legalCase}/members/{member}', [CaseMemberController::class, 'destroy'])->name('cases.members.destroy');
    // Secrecy declaration lives in routes/auth.php (cases.secrecy-declaration.store, auth-team).
});
