<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Auth;

Route::get('/', function () {
    if (Auth::check()) {
        return app(\App\Http\Controllers\Auth\LoginController::class)
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

Route::get('/office-supply-stock-flow/report', [
    \App\Http\Controllers\OfficeSupplyStockFlowReportController::class, 'download'
])->name('office-supply-stock-flow.report')->middleware(['auth']);

Route::get('/uniform-items/template', function () {
    $csv = <<<CSV
uniform_category_name,uniform_item_name,uniform_item_description,uniform_item_price,variants
Uniform Tops,Polo Shirt,Blue shirt,250,"M:10|L:15|XL:5"
Uniform Bottoms,Pants,Black pants,400,"S:5|M:10|L:8"
CSV;

    return response($csv)
        ->header('Content-Type', 'text/csv')
        ->header('Content-Disposition', 'attachment; filename="uniform_items_template.csv"');
})->name('uniform-items.template');

Route::get('/sme-items/template', function () {
    $csv = <<<CSV
sme_category_name,sme_item_name,sme_item_brand,sme_item_description,sme_item_price,variants
School Supplies,Ballpen,Pilot,Black ballpen,15,"Small:100|Medium:50"
School Supplies,Notebook,Pee,Thick notebook,45,"Small:30|Large:20"
CSV;

    return response($csv)
        ->header('Content-Type', 'text/csv')
        ->header('Content-Disposition', 'attachment; filename="sme_items_template.csv"');
})->name('sme-items.template');

Route::get('/office-supply-items/template', function () {
    $csv = <<<CSV
office_supply_category_name,office_supply_name,office_supply_description,office_supply_price,variants
Writing,Ballpen,Black ink ballpen,15,Black:100|Blue:50|Red:30
Paper,Bond Paper,A4 size bond paper,250,Short:100|Long:80
Filing,Folder,Brown folder,10,Short:50|Long:50
CSV;

    return response($csv)
        ->header('Content-Type', 'text/csv')
        ->header('Content-Disposition', 'attachment; filename="office_supply_items_template.csv"');
})->name('office-supply-items.template');