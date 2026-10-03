<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Thrown when a stock-decreasing operation requests more base units
 * than are currently available at the target warehouse.
 *
 * Blueprint §6.3 (exception contract) — canonical example from §0A.10.
 *
 * Translation key: `errors.insufficient_stock`
 * Context: variant, requested, available
 *
 * Thrown from (blueprint §6):
 *   - InventoryService::directTransfer() — the source-warehouse
 *     availability gate for a direct transfer (per variant, summed
 *     across lines, against batched availability).
 *   - InventoryService::dispatchTransfer() — the source-warehouse
 *     availability gate for a requisition dispatch, with this
 *     requisition's own reservation excluded.
 *   - SalesService::dispatchSale() — the dispatching-warehouse
 *     availability gate for a sales dispatch, with this order's own
 *     reservation excluded.
 *
 * Presentation layer (§0A.10, §21.1 `ScanForm`):
 *   Notification::make()
 *       ->danger()
 *       ->title(__('errors.insufficient_stock.title'))
 *       ->body(__('errors.insufficient_stock.body', $e->context()))
 *       ->send();
 *
 * The context keys intentionally mirror the §6.3 key-catalogue row
 * `errors.insufficient_stock → variant, requested, available` so that
 * `lang/{locale}/errors.php` `errors.insufficient_stock.body` can
 * interpolate `:variant`, `:requested`, and `:available` directly.
 *
 * Extends DomainErrorException (§6.3) so the type is both a
 * \DomainException (existing catch blocks keep working) and carries
 * the stable translation key + machine context for the presentation
 * layer to resolve.
 */
class InsufficientStockException extends DomainErrorException
{
    public function __construct(
        public readonly int $variantId,
        public readonly int $warehouseId,
        public readonly int $requested,
        public readonly int $available,
    ) {
        parent::__construct('errors.insufficient_stock', [
            'variant' => $variantId,
            'requested' => $requested,
            'available' => $available,
        ]);
    }
}
