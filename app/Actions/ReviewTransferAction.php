<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\TransferOrderStatus;
use App\Models\TransferOrder;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ReviewTransferAction
{
    public function __construct(
        public readonly AuditService $auditService,
    ) {}

    /**
     * @param  array<int, array{transfer_order_item_id: int, approved_quantity: int}>  $items
     *
     * @throws RuntimeException
     */
    public function review(TransferOrder $order, User $user, array $items, ?string $notes = null): void
    {
        if (! $order->status->canBeReviewed()) {
            throw new RuntimeException('This transfer order cannot be reviewed in its current status.');
        }

        if (! $order->canBeReviewedBy($user)) {
            throw new RuntimeException('You are not authorized to review this transfer order.');
        }

        DB::transaction(function () use ($order, $user, $items, $notes): void {
            $order->load('items');

            foreach ($items as $itemData) {
                $item = $order->items->firstWhere('id', $itemData['transfer_order_item_id']);

                if ($item === null) {
                    throw new RuntimeException("Transfer order item not found: {$itemData['transfer_order_item_id']}");
                }

                $approvedQty = $itemData['approved_quantity'];
                $requestedQty = $item->requested_quantity;

                if ($approvedQty < 0) {
                    throw new RuntimeException('Approved quantity cannot be negative.');
                }

                $itemStatus = 'approved';

                if ($approvedQty === 0) {
                    $itemStatus = 'removed';
                } elseif ($approvedQty < $requestedQty) {
                    $itemStatus = 'modified';
                }

                $item->update([
                    'approved_quantity' => $approvedQty,
                    'item_status' => $itemStatus,
                ]);
            }

            $oldStatus = $order->status->value;

            $newStatus = match ($order->status) {
                TransferOrderStatus::Requested => TransferOrderStatus::UnderReviewFulfiller,
                TransferOrderStatus::UnderReviewFulfiller => TransferOrderStatus::UnderReviewRequestor,
                TransferOrderStatus::UnderReviewRequestor => TransferOrderStatus::UnderReviewFulfiller,
                default => throw new RuntimeException('Cannot review this transfer order in its current status.'),
            };

            $order->update([
                'status' => $newStatus,
                'notes' => $notes ? ($order->notes ? $order->notes."\n\nReview notes: {$notes}" : "Review notes: {$notes}") : $order->notes,
            ]);

            $this->auditService->record(
                order: $order,
                user: $user,
                action: 'reviewed',
                changes: [
                    'status' => ['old' => $oldStatus, 'new' => $newStatus->value],
                    'items' => array_map(fn ($itemData) => [
                        'item_id' => $itemData['transfer_order_item_id'],
                        'approved_quantity' => $itemData['approved_quantity'],
                    ], $items),
                ],
            );
        });
    }
}
