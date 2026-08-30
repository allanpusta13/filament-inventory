<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\TransferOrderStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\TransferOrder;
use App\Models\User;
use App\Services\AuditService;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ConfirmTransferAction
{
    public function __construct(
        public readonly InventoryService $inventoryService,
        public readonly AuditService $auditService,
    ) {}

    /**
     * @throws InsufficientStockException
     * @throws RuntimeException
     */
    public function confirm(TransferOrder $order, User $user): void
    {
        if (! $order->status->canBeConfirmed()) {
            throw new RuntimeException('This transfer order cannot be confirmed in its current status.');
        }

        if (! $order->canBeConfirmedBy($user)) {
            throw new RuntimeException('You are not authorized to confirm this transfer order.');
        }

        DB::transaction(function () use ($order, $user): void {
            $order->load('items');

            foreach ($order->items as $item) {
                if ($item->item_status === 'removed') {
                    continue;
                }

                $this->inventoryService->lockStockForProduct(
                    $item->product_id,
                    $order->sender_branch_id,
                );

                $available = $this->inventoryService->availableForNegotiation(
                    $item->product_id,
                    $order->sender_branch_id,
                );

                $requestedQty = $item->approved_quantity ?? $item->requested_quantity;

                if ($available < $requestedQty) {
                    throw new InsufficientStockException(
                        productId: $item->product_id,
                        warehouseId: $order->sender_branch_id,
                        requested: $requestedQty,
                        available: $available,
                    );
                }

                if ($item->approved_quantity === null) {
                    $item->update([
                        'approved_quantity' => $item->requested_quantity,
                        'item_status' => 'approved',
                    ]);
                }
            }

            $oldStatus = $order->status->value;

            $order->update([
                'status' => TransferOrderStatus::Confirmed,
            ]);

            $this->auditService->record(
                order: $order,
                user: $user,
                action: 'confirmed',
                changes: [
                    'status' => ['old' => $oldStatus, 'new' => TransferOrderStatus::Confirmed->value],
                ],
            );
        });
    }
}
