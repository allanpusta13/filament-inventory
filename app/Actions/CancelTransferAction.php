<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\TransferOrderStatus;
use App\Models\TransferOrder;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CancelTransferAction
{
    public function __construct(
        public readonly AuditService $auditService,
    ) {}

    /**
     * @throws RuntimeException
     */
    public function cancel(TransferOrder $order, User $user, ?string $reason = null): void
    {
        if (! $order->status->canBeCancelled()) {
            throw new RuntimeException('This transfer order cannot be cancelled in its current status.');
        }

        if (! $order->canBeCancelledBy($user)) {
            throw new RuntimeException('You are not authorized to cancel this transfer order.');
        }

        DB::transaction(function () use ($order, $user, $reason): void {
            $oldStatus = $order->status->value;

            $order->update([
                'status' => TransferOrderStatus::Cancelled,
                'notes' => $reason ? ($order->notes ? $order->notes."\n\nCancellation reason: {$reason}" : "Cancellation reason: {$reason}") : $order->notes,
            ]);

            $changes = [
                'status' => ['old' => $oldStatus, 'new' => TransferOrderStatus::Cancelled->value],
            ];

            if ($reason !== null) {
                $changes['cancellation_reason'] = ['old' => null, 'new' => $reason];
            }

            $this->auditService->record(
                order: $order,
                user: $user,
                action: 'cancelled',
                changes: $changes,
            );
        });
    }
}
