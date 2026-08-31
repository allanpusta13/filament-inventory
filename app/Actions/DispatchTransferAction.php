<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\MovementType;
use App\Enums\TransferOrderStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\TransferOrder;
use App\Models\User;
use App\Services\AuditService;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class DispatchTransferAction
{
    public function __construct(
        public readonly InventoryService $inventoryService,
        public readonly AuditService $auditService,
    ) {}

    /**
     * @throws InsufficientStockException
     * @throws RuntimeException
     */
    public function dispatch(TransferOrder $order, User $user): void
    {
        if ($order->status !== TransferOrderStatus::Confirmed) {
            throw new RuntimeException('Only confirmed transfer orders can be dispatched.');
        }

        if (! $order->canBeDispatchedBy($user)) {
            throw new RuntimeException('You are not authorized to dispatch this transfer order.');
        }

        if ($order->items()->count() === 0) {
            throw new RuntimeException('Cannot dispatch a transfer order with no items.');
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

                $available = $this->inventoryService->currentQuantity(
                    $item->product_id,
                    $order->sender_branch_id,
                );

                $approvedQty = $item->approved_quantity ?? $item->requested_quantity;

                if ($available < $approvedQty) {
                    throw new InsufficientStockException(
                        productId: $item->product_id,
                        warehouseId: $order->sender_branch_id,
                        requested: $approvedQty,
                        available: $available,
                    );
                }

                $variantId = \App\Models\ProductVariant::where('product_id', $item->product_id)->value('id');

                if ($variantId === null) {
                    throw new RuntimeException("No variant found for product {$item->product_id}.");
                }

                $this->inventoryService->recordMovement(
                    variantId: $variantId,
                    warehouseId: $order->sender_branch_id,
                    type: MovementType::TransferOut,
                    baseQuantity: -$approvedQty,
                    reference: $order->reference_number,
                );
            }

            $oldStatus = $order->status->value;

            $order->update([
                'status' => TransferOrderStatus::Dispatched,
                'dispatched_by' => $user->id,
                'dispatched_at' => now(),
            ]);

            $this->auditService->record(
                order: $order,
                user: $user,
                action: 'dispatched',
                changes: [
                    'status' => ['old' => $oldStatus, 'new' => TransferOrderStatus::Dispatched->value],
                    'dispatched_at' => ['old' => null, 'new' => now()->toIso8601String()],
                ],
            );
        });
    }
}
