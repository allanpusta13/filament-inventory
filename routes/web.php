<?php

declare(strict_types=1);

use App\Http\Controllers\TransferNoteController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::get('/transfer-notes/{order}', TransferNoteController::class)
    ->name('transfer-notes.show')
    ->middleware('auth');
Route::get('/transfers/scan/{transferRequisition}', function (TransferRequisition $transferRequisition) {
    try {
        if (! request()->hasValidSignature()) {
            Notification::make()->title('Expired or Invalid Signature')->danger()->send();
            return redirect()->route('filament.admin.pages.dashboard');
        }
        // Proceed to redirect into Filament View page with ?scan=1
        return redirect()->route('filament.resources.transfer-requisitions.view', [
            'transferRequisition' => $transferRequisition->id,
            'scan' => 1
        ]);
    } catch (InvalidSignatureException $e) {
        Notification::make()->title('Expired or Invalid Signature')->danger()->send();
        return redirect()->route('filament.admin.pages.dashboard');
    }
})->name('stn.scan');
