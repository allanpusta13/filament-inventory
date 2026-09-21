<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMovementType;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    /**
     * Order a purchase order - change status from Draft to Ordered
     */
    public function orderPurchase(PurchaseOrder $purchaseOrder): void
    {
        DB::transaction(function () use ($purchaseOrder) {
            $purchaseOrder->loadMissing('items.productVariant');

            if ($purchaseOrder->status !== PurchaseOrderStatus::Draft) {
                throw ValidationException::withMessages([
                    'status' => 'Only draft purchase orders can be ordered.',
                ]);
            }

            if ($purchaseOrder->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Cannot order a purchase order with no line items.',
                ]);
            }

            $purchaseOrder->update([
                'status' => PurchaseOrderStatus::Ordered,
                'ordered_by' => auth()->id(),
                'ordered_at' => now(),
            ]);
        });
    }

    /**
     * Receive a purchase order - process receipt lines and create stock movements
     */
    public function receivePurchase(PurchaseOrder $purchaseOrder, array $receiptLines): void
    {
        DB::transaction(function () use ($purchaseOrder, $receiptLines) {
            $purchaseOrder->loadMissing(['items.productVariant', 'warehouse']);

            if (! in_array($purchaseOrder->status, [PurchaseOrderStatus::Ordered, PurchaseOrderStatus::PartiallyReceived])) {
                throw ValidationException::withMessages([
                    'status' => 'Only ordered or partially received purchase orders can be received.',
                ]);
            }

            $allFullyReceived = true;

            foreach ($receiptLines as $line) {
                $item = $purchaseOrder->items()->findOrFail($line['item_id']);

                $receivedBaseQty = (int) $line['received_base_qty'];
                $unitName = $line['unit_name'];
                $unitRatio = (int) $line['unit_ratio'];
                $notes = $line['notes'] ?? null;

                $newReceivedBaseQty = $item->received_base_qty + $receivedBaseQty;

                if ($newReceivedBaseQty > $item->ordered_base_qty) {
                    throw ValidationException::withMessages([
                        "items.{$item->id}.received_base_qty" => "Received quantity cannot exceed ordered quantity ({$item->ordered_base_qty} base units).",
                    ]);
                }

                StockMovement::create([
                    'product_variant_id' => $item->product_variant_id,
                    'warehouse_id' => $purchaseOrder->warehouse_id,
                    'type' => StockMovementType::Purchase,
                    'base_qty' => $receivedBaseQty,
                    'unit_name' => $unitName,
                    'unit_ratio' => $unitRatio,
                    'reference_type' => PurchaseOrder::class,
                    'reference_id' => $purchaseOrder->id,
                    'notes' => $notes,
                    'created_by' => auth()->id(),
                ]);

                $item->update([
                    'received_base_qty' => $newReceivedBaseQty,
                ]);

                if ($newReceivedBaseQty < $item->ordered_base_qty) {
                    $allFullyReceived = false;
                }

                if ($purchaseOrder->update_cost_price) {
                    $productVariant = ProductVariant::findOrFail($item->product_variant_id);
                    $productVariant->prices()->update(['is_current' => false]);

                    $productVariant->prices()->create([
                        'currency' => 'PHP',
                        'price' => $item->unit_cost_price,
                        'is_current' => true,
                        'effective_from' => now(),
                        'source' => 'purchase_receipt',
                        'source_id' => $purchaseOrder->id,
                    ]);
                }
            }

            $newStatus = $allFullyReceived
                ? PurchaseOrderStatus::Completed
                : PurchaseOrderStatus::PartiallyReceived;

            $purchaseOrder->update([
                'status' => $newStatus,
                'received_by' => auth()->id(),
                'received_at' => $newStatus === PurchaseOrderStatus::Completed ? now() : $purchaseOrder->received_at,
            ]);
        });
    }

    /**
     * Cancel a purchase order - only allowed for Draft, Ordered, PartiallyReceived
     * No reversal of stock movements - cancellation only pre-dispatch/receipt
     */
    public function cancelPurchaseOrder(PurchaseOrder $purchaseOrder): void
    {
        DB::transaction(function () use ($purchaseOrder) {
            if (! in_array($purchaseOrder->status, [
                PurchaseOrderStatus::Draft,
                PurchaseOrderStatus::Ordered,
                PurchaseOrderStatus::PartiallyReceived,
            ])) {
                throw ValidationException::withMessages([
                    'status' => 'This purchase order cannot be cancelled.',
                ]);
            }

            if ($purchaseOrder->status !== PurchaseOrderStatus::Draft) {
                $receivedItems = $purchaseOrder->items()->where('received_base_qty', '>', 0)->exists();
                if ($receivedItems) {
                    throw ValidationException::withMessages([
                        'status' => 'Cannot cancel a purchase order with received items. Use returns instead.',
                    ]);
                }
            }

            $purchaseOrder->update([
                'status' => PurchaseOrderStatus::Cancelled,
                'cancelled_at' => now(),
            ]);
        });
    }
}
