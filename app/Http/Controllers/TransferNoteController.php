<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\TransferOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

final class TransferNoteController extends Controller
{
    public function __invoke(Request $request, TransferOrder $order)
    {
        Gate::authorize('view', $order);

        $qrCode = QrCode::size(140)->generate($order->reference_number);

        return view('transfer-notes.show', [
            'order' => $order->load(['sender', 'receiver', 'items.product', 'dispatchedBy', 'receivedBy', 'audits.user']),
            'qrCode' => $qrCode,
        ]);
    }
}
