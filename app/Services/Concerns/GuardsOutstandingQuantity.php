<?php

declare(strict_types=1);

namespace App\Services\Concerns;

use Exception;

/**
 * [Added v11.1] Shared by PurchaseService and SalesService. Both need to
 * reject an incoming quantity that exceeds a line item's outstandingBaseQty()
 * — PurchaseOrderItem and SalesOrderItem both expose that method with
 * identical semantics (see Model Additions section), so the guard itself
 * doesn't need to know which kind of item it's validating.
 */
trait GuardsOutstandingQuantity
{
    /**
     * @param  object{outstandingBaseQty: callable}  $item  Any model exposing outstandingBaseQty(): int
     *
     * @throws Exception if $incomingQty exceeds the item's outstanding quantity
     */
    protected function assertWithinOutstanding(object $item, int $incomingQty, string $verb, int $itemId): void
    {
        $remaining = $item->outstandingBaseQty();

        if ($incomingQty > $remaining) {
            throw new Exception(
                "Cannot {$verb} {$incomingQty} units for item #{$itemId}: only ".
                "{$remaining} units remain outstanding on this order."
            );
        }
    }
}
