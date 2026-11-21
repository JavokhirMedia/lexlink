<?php

use App\Http\Controllers\LegalMapController;
use Illuminate\Support\Facades\Route;

// Owned by the legal-map agent — see routes/web.php. Public: guests (judges) and logged-in users.
Route::get('legal-map', LegalMapController::class)->name('legal-map');
