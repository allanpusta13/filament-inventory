<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\UserRole;
use App\Events\InventoryBelowReorderPoint;
use App\Models\User;
use App\Notifications\InventoryBelowReorderPointNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * NotifyInventoryBelowReorderPoint — §22.3a.
 *
 * Admins plus staff at the affected warehouse. Carries the variant +
 * warehouse pair directly (no relation re-query needed).
 */
class NotifyInventoryBelowReorderPoint implements ShouldQueue
{
    public function handle(InventoryBelowReorderPoint $event): void
    {
        $users = User::query()
            ->where(function ($q) use ($event) {
                $q->where('role', UserRole::Admin->value)
                    ->orWhereHas('warehouses', function ($query) use ($event) {
                        $query->whereIn('warehouses.id', [$event->warehouseId]);
                    });
            })
            ->get();

        Notification::send($users, new InventoryBelowReorderPointNotification(
            $event->productVariantId,
            $event->warehouseId,
        ));
    }
}
