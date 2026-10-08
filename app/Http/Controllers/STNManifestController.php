<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TransferRequisitionStatus;
use App\Models\TransferRequisition;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

/**
 * Shipment Note (STN) — printable manifest and signed scan intake.
 *
 * Serves the two custom web routes declared in blueprint §21.1:
 *   - GET /stn/{transferRequisition}/print  → name('stn.print')  → print()
 *   - GET /stn/{transferRequisition}/scan   → name('stn.scan')   → scan()
 *
 * Security contract (blueprint §21.3):
 *   - print() authorizes via TransferRequisitionPolicy::view.
 *   - scan()  authorizes via TransferRequisitionPolicy::receive and
 *     additionally requires the requisition to be in a receivable state
 *     (Dispatched or PartiallyReceived); otherwise aborts 403.
 *   - scan() relies on the `signed` middleware (registered on the route)
 *     for tamper-proof access; the controller itself does not re-verify
 *     the signature.
 *
 * This controller MUST NOT create StockMovement, LossLedger, or InTransit
 * records directly. All intake mutations flow through
 * InventoryService::scanToReceive() (blueprint §6.2), invoked from the
 * Livewire <livewire:stn.scan-form> component embedded by the scan view.
 */
class STNManifestController extends Controller
{
    /**
     * Render the printable shipment note for a transfer requisition.
     *
     * F28 eager-loading discipline: the stn.print view accesses
     * fromWarehouse, toWarehouse, and items.productVariant. Eager-load
     * them so the view issues a bounded set of queries.
     *
     * The QR image embedded by the view is generated from a 7-day
     * temporary signed URL to stn.scan (blueprint §21.2).
     */
    public function print(TransferRequisition $transferRequisition): View
    {
        Gate::authorize('view', $transferRequisition);

        $transferRequisition->loadMissing([
            'fromWarehouse',
            'toWarehouse',
            'items.productVariant',
        ]);

        return view('stn.print', [
            'requisition' => $transferRequisition,
        ]);
    }

    /**
     * Render the signed scan intake page for a transfer requisition.
     *
     * Preconditions enforced here:
     *   1. The route is behind `auth` + `signed` middleware (§21.1).
     *   2. The acting user is authorized via TransferRequisitionPolicy::receive.
     *   3. The requisition is in a receivable state — Dispatched or
     *      PartiallyReceived. A Draft / Requested / Confirmed requisition
     *      has not left the source warehouse, so intake is meaningless and
     *      the request is rejected with 403.
     *
     * F28 eager-loading discipline: the stn.scan view accesses
     * fromWarehouse and toWarehouse for the route banner. The scan form
     * itself (Livewire component) eager-loads items.productVariant on
     * mount.
     */
    public function scan(TransferRequisition $transferRequisition): View
    {
        Gate::authorize('receive', $transferRequisition);

        $transferRequisition->loadMissing([
            'fromWarehouse',
            'toWarehouse',
        ]);

        if (! in_array($transferRequisition->status, [
            TransferRequisitionStatus::Dispatched,
            TransferRequisitionStatus::PartiallyReceived,
        ], true)) {
            abort(403);
        }

        return view('stn.scan', [
            'requisition' => $transferRequisition,
        ]);
    }
}
