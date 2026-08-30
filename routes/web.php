<?php

declare(strict_types=1);

use App\Http\Controllers\TransferNoteController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::get('/transfer-notes/{order}', TransferNoteController::class)
    ->name('transfer-notes.show')
    ->middleware('auth');
