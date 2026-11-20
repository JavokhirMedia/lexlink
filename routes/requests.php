<?php

use App\Http\Controllers\Requests\DataRequestController;
use App\Http\Controllers\Requests\ResponseController;
use Illuminate\Support\Facades\Route;

// Owned by the requests module agent — see routes/web.php

// Index / show / response are policy-guarded (admin may see metadata only).
Route::middleware(['auth', 'onboarded'])->group(function () {
    Route::get('requests', [DataRequestController::class, 'index'])->name('requests.index');
    Route::get('requests/{dataRequest}', [DataRequestController::class, 'show'])->whereNumber('dataRequest')->name('requests.show');
    Route::get('requests/{dataRequest}/payload', [ResponseController::class, 'payload'])->whereNumber('dataRequest')->name('requests.payload');
    Route::get('requests/{dataRequest}/pdf', [ResponseController::class, 'pdf'])->whereNumber('dataRequest')->name('requests.pdf');
});

Route::middleware(['auth', 'onboarded', 'role:advocate,lawyer'])->group(function () {
    Route::get('requests/create', [DataRequestController::class, 'create'])->name('requests.create');
    Route::post('requests', [DataRequestController::class, 'store'])->name('requests.store');
    Route::post('requests/check-basis', [DataRequestController::class, 'checkBasis'])->name('requests.check-basis');
    Route::post('requests/draft-text', [DataRequestController::class, 'draftText'])->middleware('throttle:20,1')->name('requests.draft-text');
    Route::put('requests/{dataRequest}', [DataRequestController::class, 'update'])->whereNumber('dataRequest')->name('requests.update');
    Route::post('requests/{dataRequest}/request-signature', [DataRequestController::class, 'requestSignature'])->whereNumber('dataRequest')->name('requests.request-signature');
    Route::post('requests/{dataRequest}/sign', [DataRequestController::class, 'sign'])->whereNumber('dataRequest')->name('requests.sign');
});
