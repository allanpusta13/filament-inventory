<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired by PurchaseService (§6.4) in two places:
 *   - orderPurchase() — at order placement (blueprint-as-written).
 *   - receivePurchase() — on full receipt only.
 *
 * ⚠ The event name suggests receipt, but §6.4 also fires it at order
 * time. See the PurchaseService class docblock for the deviation note.
 */
class PurchaseOrderReceived implements ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly int $purchaseOrderId) {}
}
