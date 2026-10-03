<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\UserRole;
use App\Events\TransferCancelled;
use App\Models\TransferRequisition;
use App\Models\User;
use App\Notifications\TransferCancelledNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * NotifyTransferCancelled — §22.3a.
 *
 * Admins plus staff assigned to the source warehouse (the side that
 * would have shipped).
 */
class NotifyTransferCancelled implements ShouldQueue
{
    public function handle(TransferCancelled $event): void
    {
        $requisition = TransferRequisition::find($event->requisitionId);
        if (! $requisition) {
            return;
        }

        $users = User::query()
            ->where(function ($q) use ($requisition) {
                $q->where('role', UserRole::Admin->value)
                    ->orWhereHas('warehouses', function ($query) use ($requisition) {
                        $query->whereIn('warehouses.id', [$requisition->from_warehouse_id]);
                    });
            })
            ->get();

        Notification::send($users, new TransferCancelledNotification(
            $requisition->id,
            $requisition->reference_code,
        ));
    }
}
