<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\UserRole;
use App\Events\LossRecorded;
use App\Models\LossLedger;
use App\Models\User;
use App\Notifications\LossRecordedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * NotifyLossRecorded — §22.3a.
 *
 * Admins, auditors, and staff assigned to the loss warehouse. Auditors
 * are included because loss events are audit-relevant.
 */
class NotifyLossRecorded implements ShouldQueue
{
    public function handle(LossRecorded $event): void
    {
        $loss = LossLedger::find($event->lossLedgerId);
        if (! $loss) {
            return;
        }

        $users = User::query()
            ->where(function ($q) use ($loss) {
                $q->whereIn('role', [UserRole::Admin->value, UserRole::Auditor->value])
                    ->orWhereHas('warehouses', function ($query) use ($loss) {
                        $query->whereIn('warehouses.id', [$loss->warehouse_id]);
                    });
            })
            ->get();

        Notification::send($users, new LossRecordedNotification($loss->id));
    }
}
