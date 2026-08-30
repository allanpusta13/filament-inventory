<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\TransferOrder;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\Request;

final class TransferNoteController extends Controller
{
    public function __invoke(Request $request, TransferOrder $order)
    {
        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_MARKUP_SVG,
            'eccLevel' => QRCode::ECC_M,
        ]);

        $qrCode = (new QRCode($options))->render($order->reference_number);

        return view('transfer-notes.show', [
            'order' => $order->load(['sender', 'receiver', 'items.product', 'dispatchedBy', 'receivedBy']),
            'qrCode' => $qrCode,
        ]);
    }
}
