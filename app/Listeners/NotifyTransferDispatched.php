<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\UserRole;
use App\Events\TransferDispatched;
use App\Models\TransferRequisition;
use App\Models\User;
use App\Notifications\TransferDispatchedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * NotifyTransferDispatched — §22.3a.
 *
 * Admins plus staff assigned to the destination warehouse (the side
 * that must receive).
 */
class NotifyTransferDispatched implements ShouldQueue
{
    public function handle(TransferDispatched $event): void
    {
        $requisition = TransferRequisition::find($event->requisitionId);
        if (! $requisition) {
            return;
        }

        $users = User::query()
            ->where(function ($q) use ($requisition) {
                $q->where('role', UserRole::Admin->value)
                    ->orWhereHas('warehouses', function ($query) use ($requisition) {
                        $query->whereIn('warehouses.id', [$requisition->to_warehouse_id]);
                    });
            })
            ->get();

        Notification::send($users, new TransferDispatchedNotification(
            $requisition->id,
            $requisition->reference_code,
        ));
    }
}
