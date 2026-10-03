<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\UserRole;
use App\Events\TransferConfirmed;
use App\Models\TransferRequisition;
use App\Models\User;
use App\Notifications\TransferConfirmedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * NotifyTransferConfirmed — §22.3a.
 *
 * Queued database notification to admins plus staff assigned to either
 * endpoint of the confirmed requisition. Does not mutate stock.
 */
class NotifyTransferConfirmed implements ShouldQueue
{
    public function handle(TransferConfirmed $event): void
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

        Notification::send($users, new TransferConfirmedNotification(
            $requisition->id,
            $requisition->reference_code,
        ));
    }
}
