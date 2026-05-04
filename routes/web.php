<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Auth;

Route::get('/', function () {
    if (Auth::check()) {
        return app(\App\Http\Controllers\Auth\LoginCOntroller::class)
            ->redirectByRole(Auth::user());
    }
    return redirect('/login');
});

Route::get('/login', [LoginController::class, 'showLogin'])
    ->name('login')
    ->middleware('guest');

Route::post('/login', [LoginController::class, 'login'])
    ->middleware('throttle:10,1');

Route::get('/logout', [LoginController::class, 'logout'])
    ->name('logout')
    ->middleware('auth');

use App\Http\Controllers\UniformIssuanceReceivingCopyController;
use App\Http\Controllers\UniformIssuanceTransmittalController;

Route::prefix('uniform-issuances')->name('uniform-issuances.')->middleware('auth')->group(function () {

    // ── Static routes FIRST (before any wildcards) ──────────────────────
    Route::get('/bulk/receiving-copy', [UniformIssuanceReceivingCopyController::class, 'bulk'])->name('bulk.receiving-copy');
    Route::get('/recipient/{recipient}/receiving-copy', [UniformIssuanceReceivingCopyController::class, 'recipient'])->name('recipient.receiving-copy');

    // ── Wildcard routes LAST ─────────────────────────────────────────────
    Route::get('/{issuance}/receiving-copy', [UniformIssuanceReceivingCopyController::class, 'issuance'])->name('receiving-copy');
    Route::get('/{issuance}/transmittal', [UniformIssuanceTransmittalController::class, 'issuance'])->name('transmittal');
    Route::get('/{issuance}/transmittal/log/{log}', [UniformIssuanceTransmittalController::class, 'fromLog'])->name('transmittal.log');

});

Route::get('/private-image/{disk}/{path}', function ($disk, $path) {
    $path = base64_decode($path);
    if (!\Storage::disk($disk)->exists($path)) {
        abort(404);
    }
    return response()->file(storage_path('app/private/' . $path));
})->middleware('auth')->name('private.image');

use App\Http\Controllers\AssetPropertyTagController;
Route::get('/assets/print-property-tags', [AssetPropertyTagController::class, 'bulk'])
    ->name('assets.property-tags.bulk')
    ->middleware('auth');

Route::get('/uniform-stock-flow/report', [
    \App\Http\Controllers\UniformStockFlowReportController::class, 'download'
])->name('uniform-stock-flow.report')->middleware(['auth']);

Route::get('/sme-stock-flow/report', [
    \App\Http\Controllers\SmeStockFlowReportController::class, 'download'
])->name('sme-stock-flow.report')->middleware(['auth']);
