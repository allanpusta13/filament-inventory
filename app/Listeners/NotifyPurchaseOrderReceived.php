<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\UserRole;
use App\Events\PurchaseOrderReceived;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Notifications\PurchaseOrderReceivedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * NotifyPurchaseOrderReceived — §22.3a.
 *
 * Admins plus staff at the receiving warehouse. Fires on both the
 * order-time dispatch and the full-receipt dispatch (§6.4).
 */
class NotifyPurchaseOrderReceived implements ShouldQueue
{
    public function handle(PurchaseOrderReceived $event): void
    {
        $order = PurchaseOrder::find($event->purchaseOrderId);
        if (! $order) {
            return;
        }

        $users = User::query()
            ->where(function ($q) use ($order) {
                $q->where('role', UserRole::Admin->value)
                    ->orWhereHas('warehouses', function ($query) use ($order) {
                        $query->whereIn('warehouses.id', [$order->warehouse_id]);
                    });
            })
            ->get();

        Notification::send($users, new PurchaseOrderReceivedNotification(
            $order->id,
            $order->reference_code,
        ));
    }
}
