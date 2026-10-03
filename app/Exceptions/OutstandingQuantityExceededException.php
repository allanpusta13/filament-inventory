<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Thrown when an operation attempts to process more units against a
 * document line than remain outstanding on that line.
 *
 * Blueprint §6.3 (exception contract).
 *
 * Translation key: `errors.outstanding_quantity_exceeded`
 * Context: item, attempted, outstanding
 *
 * Guarded by `App\Services\GuardsOutstandingQuantity` (§6.1):
 *   - assertTransferNotOverShipped()  — TransferRequisitionItem
 *   - assertPurchaseNotOverReceived() — PurchaseOrderItem
 *   - assertSaleNotOverDispatched()   — SalesOrderItem
 *
 * Also thrown directly from:
 *   - InventoryService::scanToReceive() — the per-item over-receive
 *     guard, before canonicalization/hashing, and again per applied
 *     line.
 *   - InventoryService::recordLoss() — the per-item outstanding guard,
 *     accounting for prior shortfall write-offs
 *     (`loss_ledgers.lost_base_qty`).
 *   - SalesService::recordSalesReturn() — the cumulative over-return
 *     guard against `dispatched_base_qty` minus `alreadyReturnedBaseQty()`.
 *
 * Presentation layer (§0A.10):
 *   Notification::make()
 *       ->danger()
 *       ->title(__('errors.outstanding_quantity_exceeded.title'))
 *       ->body(__('errors.outstanding_quantity_exceeded.body', $e->context()))
 *       ->send();
 *
 * The context keys intentionally mirror the §6.3 key-catalogue row
 * `errors.outstanding_quantity_exceeded → item, attempted, outstanding`
 * so that `lang/{locale}/errors.php` `...body` can interpolate
 * `:item`, `:attempted`, and `:outstanding` directly.
 *
 * `itemType` and `itemId` are stored separately so a caller can
 * branch on the concrete model class (TransferRequisitionItem,
 * PurchaseOrderItem, or SalesOrderItem) without re-parsing the
 * polymorphic `:item` context value, which is the numeric id.
 *
 * Extends DomainErrorException (§6.3) so the type is both a
 * \DomainException (existing catch blocks keep working) and carries
 * the stable translation key + machine context for the presentation
 * layer to resolve.
 */
class OutstandingQuantityExceededException extends DomainErrorException
{
    public function __construct(
        public readonly string $itemType,
        public readonly int $itemId,
        public readonly int $attempted,
        public readonly int $outstanding,
    ) {
        parent::__construct('errors.outstanding_quantity_exceeded', [
            'item' => $itemId,
            'attempted' => $attempted,
            'outstanding' => $outstanding,
        ]);
    }
}
