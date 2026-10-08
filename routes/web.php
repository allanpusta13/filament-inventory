<?php

declare(strict_types=1);

use App\Http\Controllers\STNManifestController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::middleware(['auth'])->group(function () {
    Route::get('/stn/{transferRequisition}/print', [STNManifestController::class, 'print'])
        ->name('stn.print');

    // Widget routes testing
    Route::get('/widgets/low-stock-alerts', function () {
        return view('filament.widgets.low-stock-alerts');
    })->name('widgets.low-stock-alerts');

    Route::get('/widgets/recent-movements', function () {
        return view('filament.widgets.recent-movements');
    })->name('widgets.recent-movements');
});

Route::middleware(['auth', 'signed'])->group(function () {
    Route::get('/stn/{transferRequisition}/scan', [STNManifestController::class, 'scan'])
        ->name('stn.scan');
});
