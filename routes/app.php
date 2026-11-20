<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

// Owned by module agent — see routes/web.php

Route::middleware(['auth', 'onboarded'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});
