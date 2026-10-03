<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\OutstandingQuantityExceededException;
use App\Models\PurchaseOrderItem;
use App\Models\SalesOrderItem;
use App\Models\TransferRequisitionItem;

/**
 * GuardsOutstandingQuantity — line-level outstanding-quantity gate.
 *
 * Blueprint §6.1. One method per document line type, each asserting
 * that a proposed quantity does not exceed the line's outstanding
 * base quantity. All three methods throw the same typed exception
 * (`OutstandingQuantityExceededException`, §6.3) carrying the item
 * type, item id, attempted quantity, and outstanding quantity.
 *
 * The item's own helper resolves the outstanding value:
 *   - `TransferRequisitionItem::outstandingShippedBaseQty()` (§3.8)
 *   - `PurchaseOrderItem::outstandingBaseQty()`            (§3.15)
 *   - `SalesOrderItem::outstandingBaseQty()`               (§3.17)
 *
 * Boundary semantics: `$newX > $outstanding` throws. `$newX ==
 * $outstanding` is legal — a fully-completing operation is allowed.
 * The comparison is strict on both sides: `$newX` is the *amount for
 * this operation*, not a cumulative running total. Callers that need
 * cumulative accounting (e.g. sales returns) sum prior movements
 * before calling.
 *
 * Injected via the service container (§17.1) and consumed by:
 *   - `InventoryService::dispatchTransfer()`         (§6.2)
 *   - `InventoryService::scanToReceive()`            (§6.2, for the
 *     per-line over-receive check)
 *   - `InventoryService::recordLoss()`               (§6.2)
 *   - `PurchaseService::receivePurchase()`           (§6.4)
 *   - `SalesService::dispatchSale()`                 (§6.5)
 *   - `SalesService::recordSalesReturn()`            (§6.5, for the
 *     cumulative over-return check)
 *
 * No state, no dependencies, no database access — every method is a
 * pure comparison against the item's own in-memory state.
 */
class GuardsOutstandingQuantity
{
    /**
     * Assert that a new shipped quantity does not exceed the
     * requisition item's outstanding shipped base qty.
     *
     * @throws OutstandingQuantityExceededException
     */
    public function assertTransferNotOverShipped(TransferRequisitionItem $item, int $newShipped): void
    {
        $outstanding = $item->outstandingShippedBaseQty();

        if ($newShipped > $outstanding) {
            throw new OutstandingQuantityExceededException(
                itemType: $item::class,
                itemId: (int) $item->id,
                attempted: $newShipped,
                outstanding: $outstanding,
            );
        }
    }

    /**
     * Assert that a new received quantity does not exceed the
     * purchase-order item's outstanding base qty.
     *
     * @throws OutstandingQuantityExceededException
     */
    public function assertPurchaseNotOverReceived(PurchaseOrderItem $item, int $newReceived): void
    {
        $outstanding = $item->outstandingBaseQty();

        if ($newReceived > $outstanding) {
            throw new OutstandingQuantityExceededException(
                itemType: $item::class,
                itemId: (int) $item->id,
                attempted: $newReceived,
                outstanding: $outstanding,
            );
        }
    }

    /**
     * Assert that a new dispatch quantity does not exceed the
     * sales-order item's outstanding base qty.
     *
     * @throws OutstandingQuantityExceededException
     */
    public function assertSaleNotOverDispatched(SalesOrderItem $item, int $newDispatch): void
    {
        $outstanding = $item->outstandingBaseQty();

        if ($newDispatch > $outstanding) {
            throw new OutstandingQuantityExceededException(
                itemType: $item::class,
                itemId: (int) $item->id,
                attempted: $newDispatch,
                outstanding: $outstanding,
            );
        }
    }
}
