<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DirectTransfer;
use App\Models\TransferRequisition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

final class STNManifestController extends Controller
{
    public function print(Request $request, TransferRequisition $transferRequisition)
    {
        $user = $request->user();

        if (
            ! $user->hasAccessToWarehouse($transferRequisition->to_warehouse_id) &&
            ! $user->hasAccessToWarehouse($transferRequisition->from_warehouse_id)
        ) {
            abort(403, 'You are not assigned to the origin or destination warehouse.');
        }

        $signedUrl = URL::temporarySignedRoute(
            'stn.scan',
            now()->addDays(30),
            ['transferRequisition' => $transferRequisition->id]
        );

        $qrCode = QrCode::size(120)->generate($signedUrl);

        $requisition = $transferRequisition->load([
            'items.variant',
            'items.substituteVariant',
            'fromWarehouse',
            'toWarehouse',
            'requestedBy',
            'dispatchedBy',
            'receivedBy',
        ]);

        return view('pdf.stn-manifest', [
            'requisition' => $requisition,
            'qrCode' => $qrCode,
        ]);
    }

    public function printDirectTransfer(Request $request, DirectTransfer $directTransfer)
    {
        $user = $request->user();

        if (
            ! $user->hasAccessToWarehouse($directTransfer->to_warehouse_id) &&
            ! $user->hasAccessToWarehouse($directTransfer->from_warehouse_id)
        ) {
            abort(403, 'You are not assigned to the origin or destination warehouse.');
        }

        $signedUrl = URL::temporarySignedRoute(
            'stn.direct-scan',
            now()->addDays(30),
            ['directTransfer' => $directTransfer->id]
        );

        $qrCode = QrCode::size(120)->generate($signedUrl);

        $transfer = $directTransfer->load([
            'items.variant',
            'fromWarehouse',
            'toWarehouse',
            'executedByUser',
        ]);

        return view('pdf.direct-transfer-manifest', [
            'transfer' => $transfer,
            'qrCode' => $qrCode,
        ]);
    }
}
