<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a transfer requisition reaches a terminal intake state —
 * `Completed` or `ClosedWithLoss` (§6.2 scanToReceive). Does NOT fire
 * on `PartiallyReceived` (no separate partial event exists).
 */
class TransferReceived implements ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly int $requisitionId) {}
}
