<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\UserRole;
use App\Events\SalesOrderDispatched;
use App\Models\SalesOrder;
use App\Models\User;
use App\Notifications\SalesOrderDispatchedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * NotifySalesOrderDispatched — §22.3a.
 *
 * Admins plus staff at the dispatching warehouse. Fires on full
 * dispatch only (§6.5).
 */
class NotifySalesOrderDispatched implements ShouldQueue
{
    public function handle(SalesOrderDispatched $event): void
    {
        $order = SalesOrder::find($event->salesOrderId);
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

        Notification::send($users, new SalesOrderDispatchedNotification(
            $order->id,
            $order->reference_code,
        ));
    }
}
