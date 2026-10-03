<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\LossLedger;
use Illuminate\Notifications\Notification;

/**
 * §22.3b. Database notification for `LossRecorded`.
 *
 * Carries only the loss ledger id. Resolves qty + sku lazily inside
 * `toDatabase()` so a queued payload stays serializable.
 */
class LossRecordedNotification extends Notification
{
    public function __construct(public readonly int $lossLedgerId) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $loss = LossLedger::with('productVariant')->find($this->lossLedgerId);

        $qty = $loss ? $loss->lost_base_qty + $loss->damaged_base_qty : 0;
        $sku = $loss?->productVariant?->sku ?? (string) $this->lossLedgerId;

        return [
            'loss_ledger_id' => $this->lossLedgerId,
            'message' => __('notifications.loss_recorded.body', [
                'qty' => $qty,
                'sku' => $sku,
            ]),
        ];
    }
}
