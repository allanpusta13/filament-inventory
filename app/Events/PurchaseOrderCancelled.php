<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a purchase order transitions to Cancelled (§6.4
 * cancelPurchaseOrder).
 *
 * ⚠ GAP-FILL: §22.1's event catalogue and §25's file map do NOT list
 * this event, but §6.4 fires it. Generated here so the service call
 * resolves at runtime. Recommend adding this event to §22.1 / §22.3a /
 * §22.3b / §25 in the next blueprint revision.
 */
class PurchaseOrderCancelled implements ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly int $purchaseOrderId) {}
}
