<?php

declare(strict_types=1);

use App\Http\Controllers\ScanReceiptController;
use App\Http\Controllers\STNManifestController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::middleware(['auth'])->group(function () {
    Route::get('/transfers/scan/{transferRequisition}', [ScanReceiptController::class, 'show'])
        ->name('stn.scan')
        ->middleware(['throttle:scans', 'signed']);

    Route::post('/transfers/scan/{transferRequisition}/receive', [ScanReceiptController::class, 'receive'])
        ->name('stn.scan.receive')
        ->middleware(['throttle:scans', 'signed']);

    Route::get('/stn/print/{transferRequisition}', [STNManifestController::class, 'print'])
        ->name('stn.print');

    Route::get('/stn/print-direct/{movement}', [STNManifestController::class, 'printDirectTransfer'])
        ->name('stn.print-direct');

    // Widget routes for testing
    Route::get('/widgets/low-stock-alerts', function () {
        return view('filament.widgets.low-stock-alerts');
    })->name('widgets.low-stock-alerts');

    Route::get('/widgets/recent-movements', function () {
        return view('filament.widgets.recent-movements');
    })->name('widgets.recent-movements');
});
