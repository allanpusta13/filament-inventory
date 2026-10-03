<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\UserRole;
use App\Events\TransferReceived;
use App\Models\TransferRequisition;
use App\Models\User;
use App\Notifications\TransferReceivedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * NotifyTransferReceived — §22.3a.
 *
 * Admins plus staff at both endpoints (the requisition is now closed).
 */
class NotifyTransferReceived implements ShouldQueue
{
    public function handle(TransferReceived $event): void
    {
        $requisition = TransferRequisition::find($event->requisitionId);
        if (! $requisition) {
            return;
        }

        $warehouseIds = [$requisition->from_warehouse_id, $requisition->to_warehouse_id];

        $users = User::query()
            ->where(function ($q) use ($warehouseIds) {
                $q->where('role', UserRole::Admin->value)
                    ->orWhereHas('warehouses', function ($query) use ($warehouseIds) {
                        $query->whereIn('warehouses.id', $warehouseIds);
                    });
            })
            ->get();

        Notification::send($users, new TransferReceivedNotification(
            $requisition->id,
            $requisition->reference_code,
        ));
    }
}
