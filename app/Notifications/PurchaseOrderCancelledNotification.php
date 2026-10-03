<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * GAP-FILL. Database notification for the gap-fill
 * `PurchaseOrderCancelled` event.
 *
 * ⚠ §22.3b does NOT enumerate a notification for purchase-order
 * cancellation, but §6.4 fires the event and §17.4's EventServiceProvider
 * requires a listener, which requires this class. Recommend adding it
 * (and its `notifications.purchase_order_cancelled.body` translation
 * key) to §22.3b / §0A.2a / §25 in the next blueprint revision.
 *
 * Translation key: `notifications.purchase_order_cancelled.body` —
 * MUST be added to `lang/{locale}/notifications.php` for every locale.
 */
class PurchaseOrderCancelledNotification extends Notification
{
    public function __construct(
        public readonly int $purchaseOrderId,
        public readonly ?string $referenceCode = null,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'purchase_order_id' => $this->purchaseOrderId,
            'reference_code' => $this->referenceCode,
            'message' => __('notifications.purchase_order_cancelled.body', [
                'reference' => $this->referenceCode ?? (string) $this->purchaseOrderId,
            ]),
        ];
    }
}
