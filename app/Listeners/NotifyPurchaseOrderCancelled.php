<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\UserRole;
use App\Events\PurchaseOrderCancelled;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Notifications\PurchaseOrderCancelledNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * NotifyPurchaseOrderCancelled — GAP-FILL listener.
 *
 * ⚠ §22.3a does NOT enumerate a listener for PurchaseOrderCancelled,
 * but §6.4 fires the event. Generated here so the EventServiceProvider
 * mapping resolves. Recommend adding this listener to §22.3a in the
 * next blueprint revision.
 *
 * Notifies admins plus staff at the cancelling warehouse.
 */
class NotifyPurchaseOrderCancelled implements ShouldQueue
{
    public function handle(PurchaseOrderCancelled $event): void
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

        Notification::send($users, new PurchaseOrderCancelledNotification(
            $order->id,
            $order->reference_code,
        ));
    }
}
