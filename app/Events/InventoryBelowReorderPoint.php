<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a stock-decreasing operation crosses the variant's
 * reorder-point threshold at a warehouse (§6.2 dispatchTransfer,
 * §6.5 dispatchSale). Carries the pair needed to identify the alert.
 */
class InventoryBelowReorderPoint implements ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $productVariantId,
        public readonly int $warehouseId,
    ) {}
}
