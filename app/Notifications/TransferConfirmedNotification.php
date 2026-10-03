<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * §22.3b. Database notification for `TransferConfirmed`.
 *
 * Channel: database-only (§22.3b v1 owner approval). Body resolves
 * through `notifications.transfer_confirmed.body` (§0A.8 / §0A.2a).
 */
class TransferConfirmedNotification extends Notification
{
    public function __construct(
        public readonly int $requisitionId,
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
            'requisition_id' => $this->requisitionId,
            'reference_code' => $this->referenceCode,
            'message' => __('notifications.transfer_confirmed.body', [
                'reference' => $this->referenceCode ?? (string) $this->requisitionId,
            ]),
        ];
    }
}
