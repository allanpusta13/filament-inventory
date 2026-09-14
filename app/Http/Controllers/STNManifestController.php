<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\StockMovement;
use App\Models\TransferRequisition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

final class STNManifestController extends Controller
{
    /**
     * Prints the multi-stage Stock Transfer Note (STN) Manifest.
     */
    public function print(Request $request, TransferRequisition $requisition)
    {
        $user = Auth::user();

        // Enforce RBAC site scope
        if (! $user->canAccessWarehouse($requisition->toWarehouse) && ! $user->canAccessWarehouse($requisition->fromWarehouse)) {
            abort(403, 'Unauthorized access to this location manifest.');
        }

        // Generate 7-day secure temporary signed scan URL (Blueprint v10)
        $signedUrl = URL::temporarySignedRoute(
            'stn.scan',
            now()->addDays(7),
            ['transferRequisition' => $requisition->id]
        );

        $qrCodeSvg = QrCode::size(120)->generate($signedUrl);

        return view('pdf.stn-manifest', [
            'requisition' => $requisition->load('items.productVariant', 'fromWarehouse', 'toWarehouse', 'requestedBy'),
            'qrCode' => $qrCodeSvg,
        ]);
    }

    /**
     * Prints the Instant Direct Transfer Manifest.
     */
    public function printDirectTransfer(Request $request, StockMovement $movement)
    {
        abort_unless($movement->type === 'transfer_out', 404);

        $user = Auth::user();
        $inLeg = StockMovement::where('related_movement_id', $movement->id)->firstOrFail();

        // Enforce RBAC physical scope
        if (! $user->canAccessWarehouse($movement->warehouse) && ! $user->canAccessWarehouse($inLeg->warehouse)) {
            abort(403);
        }

        return view('pdf.direct-transfer-manifest', [
            'outLeg' => $movement->load('variant', 'warehouse', 'creator'),
            'inLeg' => $inLeg->load('warehouse'),
        ]);
    }
}
