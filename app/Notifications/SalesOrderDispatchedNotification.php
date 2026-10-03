<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * §22.3b. Database notification for `SalesOrderDispatched`.
 */
class SalesOrderDispatchedNotification extends Notification
{
    public function __construct(
        public readonly int $salesOrderId,
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
            'sales_order_id' => $this->salesOrderId,
            'reference_code' => $this->referenceCode,
            'message' => __('notifications.sales_order_dispatched.body', [
                'reference' => $this->referenceCode ?? (string) $this->salesOrderId,
            ]),
        ];
    }
}
