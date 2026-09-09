<?php

declare(strict_types=1);

use App\Http\Controllers\ScanReceiptController;
use App\Http\Controllers\STNManifestController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::middleware(['auth'])->group(function () {
    Route::get('/transfers/scan/{transferRequisition}', [ScanReceiptController::class, 'show'])
        ->name('stn.scan')
        ->middleware('throttle:scans');

    Route::post('/transfers/scan/{transferRequisition}/receive', [ScanReceiptController::class, 'receive'])
        ->name('stn.scan.receive')
        ->middleware('throttle:scans');

    Route::get('/stn/print/{transferRequisition}', [STNManifestController::class, 'print'])
        ->name('stn.print');

    Route::get('/stn/print-direct/{movement}', [STNManifestController::class, 'printDirectTransfer'])
        ->name('stn.print-direct');
});
