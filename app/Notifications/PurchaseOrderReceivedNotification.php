<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * §22.3b. Database notification for `PurchaseOrderReceived`.
 *
 * Fired by PurchaseService on order placement (§6.4) AND on full
 * receipt. The notification carries the same reference in both cases;
 * the message body is identical either way.
 */
class PurchaseOrderReceivedNotification extends Notification
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
            'message' => __('notifications.purchase_order_received.body', [
                'reference' => $this->referenceCode ?? (string) $this->purchaseOrderId,
            ]),
        ];
    }
}
