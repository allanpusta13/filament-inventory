<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\MovementType;
use App\Enums\TransferOrderStatus;
use App\Models\TransferOrder;
use App\Models\User;
use App\Services\AuditService;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class ReceiveTransferAction
{
    public function __construct(
        public readonly InventoryService $inventoryService,
        public readonly AuditService $auditService,
    ) {}

    /**
     * @param  array<int, array{transfer_order_item_id: int, quantity_received: int, damaged_quantity?: int, variance_reason?: string}>  $items
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function receive(TransferOrder $order, User $user, array $items): void
    {
        if ($order->status !== TransferOrderStatus::Dispatched) {
            throw new RuntimeException('Only dispatched transfer orders can be received.');
        }

        if (! $order->canBeReceivedBy($user)) {
            throw new RuntimeException('You are not authorized to receive this transfer order.');
        }

        DB::transaction(function () use ($order, $user, $items): void {
            $order->load('items');

            foreach ($items as $itemData) {
                $item = $order->items->firstWhere('id', $itemData['transfer_order_item_id']);

                if ($item === null) {
                    throw new RuntimeException("Transfer order item not found: {$itemData['transfer_order_item_id']}");
                }

                $receivedQty = $itemData['quantity_received'];
                $damagedQty = $itemData['damaged_quantity'] ?? 0;
                $approvedQty = $item->approved_quantity ?? $item->requested_quantity;

                if ($receivedQty + $damagedQty > $approvedQty) {
                    throw new InvalidArgumentException(
                        "Received ({$receivedQty}) + Damaged ({$damagedQty}) exceeds approved quantity ({$approvedQty}) for product {$item->product_id}."
                    );
                }

                $this->inventoryService->lockStockForProduct(
                    $item->product_id,
                    $order->receiver_branch_id,
                );

                if ($receivedQty > 0) {
                    $variantId = \App\Models\ProductVariant::where('product_id', $item->product_id)->value('id');

                    if ($variantId === null) {
                        throw new RuntimeException("No variant found for product {$item->product_id}.");
                    }

                    $this->inventoryService->recordMovement(
                        variantId: $variantId,
                        warehouseId: $order->receiver_branch_id,
                        type: MovementType::TransferIn,
                        baseQuantity: $receivedQty,
                        reference: $order->reference_number,
                    );
                }

                $item->update([
                    'received_quantity' => $receivedQty,
                    'damaged_quantity' => $damagedQty,
                    'variance_reason' => $itemData['variance_reason'] ?? null,
                    'item_status' => $receivedQty === $approvedQty ? 'approved' : 'modified',
                ]);
            }

            $oldStatus = $order->status->value;

            $order->update([
                'status' => TransferOrderStatus::Received,
                'received_by' => $user->id,
                'received_at' => now(),
            ]);

            $this->auditService->record(
                order: $order,
                user: $user,
                action: 'received',
                changes: [
                    'status' => ['old' => $oldStatus, 'new' => TransferOrderStatus::Received->value],
                    'received_at' => ['old' => null, 'new' => now()->toIso8601String()],
                    'items' => array_map(fn ($itemData) => [
                        'item_id' => $itemData['transfer_order_item_id'],
                        'quantity_received' => $itemData['quantity_received'],
                        'damaged_quantity' => $itemData['damaged_quantity'] ?? 0,
                    ], $items),
                ],
            );
        });
    }
}
