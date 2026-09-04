<?php

declare(strict_types=1);

use App\Filament\Resources\DirectTransfers\DirectTransferResource;
use App\Http\Controllers\ScanReceiptController;
use App\Http\Controllers\STNManifestController;
use App\Http\Controllers\TransferNoteController;
use App\Models\DirectTransfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::get('/transfer-notes/{order}', TransferNoteController::class)
    ->name('transfer-notes.show')
    ->middleware('auth');

Route::middleware(['auth'])->group(function () {
    Route::get('/transfers/scan/{transferRequisition}', [ScanReceiptController::class, 'show'])
        ->name('stn.scan')
        ->middleware('throttle:scans');

    Route::post('/transfers/scan/{transferRequisition}/receive', [ScanReceiptController::class, 'receive'])
        ->name('stn.scan.receive')
        ->middleware('throttle:scans');

    Route::get('/transfers/scan-direct/{directTransfer}', function (Request $request, DirectTransfer $directTransfer) {
        if (! $request->hasValidSignature()) {
            session()->flash('notification', [
                'title' => 'Signature Expired or Invalid',
                'body' => 'The scanned Direct Transfer Note is older than 30 days or has been modified. Please generate a fresh manifest.',
                'type' => 'danger',
            ]);

            return redirect()->route('filament.admin.pages.dashboard');
        }

        $user = auth()->user();
        if (
            ! $user->hasAccessToWarehouse($directTransfer->to_warehouse_id) &&
            ! $user->hasAccessToWarehouse($directTransfer->from_warehouse_id)
        ) {
            session()->flash('notification', [
                'title' => 'Access Denied',
                'body' => 'You are not assigned to the origin or destination warehouse linked to this direct transfer.',
                'type' => 'warning',
            ]);

            return redirect()->route('filament.admin.pages.dashboard');
        }

        return redirect(
            DirectTransferResource::getUrl('view', [
                'record' => $directTransfer->id,
            ])
        );
    })
        ->name('stn.direct-scan')
        ->middleware('throttle:scans');

    Route::get('/stn/print/{transferRequisition}', [STNManifestController::class, 'print'])
        ->name('stn.print');

    Route::get('/stn/print-direct/{directTransfer}', [STNManifestController::class, 'printDirectTransfer'])
        ->name('stn.print-direct');
});
