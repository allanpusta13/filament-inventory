<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\TransferOrderStatus;
use App\Models\TransferOrder;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class SubmitTransferAction
{
    public function __construct(
        public readonly AuditService $auditService,
    ) {}

    /**
     * @throws RuntimeException
     */
    public function submit(TransferOrder $order, User $user): void
    {
        if ($order->status !== TransferOrderStatus::Draft) {
            throw new RuntimeException('Only draft transfer orders can be submitted.');
        }

        if (! $order->canBeSubmittedBy($user)) {
            throw new RuntimeException('You are not authorized to submit this transfer order.');
        }

        if ($order->items()->count() === 0) {
            throw new RuntimeException('Cannot submit a transfer order with no items.');
        }

        DB::transaction(function () use ($order, $user): void {
            $oldStatus = $order->status->value;

            $order->update([
                'status' => TransferOrderStatus::Requested,
            ]);

            $this->auditService->record(
                order: $order,
                user: $user,
                action: 'submitted',
                changes: [
                    'status' => ['old' => $oldStatus, 'new' => TransferOrderStatus::Requested->value],
                ],
            );
        });
    }
}
