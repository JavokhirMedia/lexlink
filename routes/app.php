<?php

use Illuminate\Support\Facades\Route;

// Owned by module agent — see routes/web.php

Route::middleware(['auth', 'onboarded'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard'); // billing agent replaces with DashboardController
});
