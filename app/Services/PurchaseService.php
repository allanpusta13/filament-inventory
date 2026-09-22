<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMovementType;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use Exception;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    use Concerns\GuardsOutstandingQuantity;

    public function orderPurchase(PurchaseOrder $po): void
    {
        if ($po->status !== PurchaseOrderStatus::Draft) {
            throw new Exception("Purchase order must be in draft to be ordered. Current: {$po->status->value}.");
        }

        if ($po->items()->count() === 0) {
            throw new Exception('Purchase order must have at least one line item.');
        }

        $po->update([
            'status' => PurchaseOrderStatus::Ordered,
            'ordered_at' => now(),
        ]);
    }

    /**
     * Receives a purchase order, in full or in part. Mirrors
     * InventoryService::scanToReceive()'s incremental-receipt pattern,
     * but without the loss/damage machinery — see Addendum Principle A7.
     *
     * $receivedItemsData: [purchase_order_item_id => received_base_qty, ...]
     */
    public function receivePurchase(int $purchaseOrderId, array $receivedItemsData): void
    {
        DB::transaction(function () use ($purchaseOrderId, $receivedItemsData) {
            $po = PurchaseOrder::with('items.productVariant.currentPrice')
                ->lockForUpdate()
                ->findOrFail($purchaseOrderId);

            $allowed = [PurchaseOrderStatus::Ordered, PurchaseOrderStatus::PartiallyReceived];

            if (! in_array($po->status, $allowed, true)) {
                throw new Exception("Purchase order not in a receivable state. Current: {$po->status->value}.");
            }

            foreach ($po->items as $item) {
                if (! isset($receivedItemsData[$item->id])) {
                    continue;
                }

                $incomingQty = (int) $receivedItemsData[$item->id];

                if ($incomingQty <= 0) {
                    continue;
                }

                // [EDGE CASE] Over-receipt guard: never allow receiving more
                // than was ordered. Supplier over-shipments must be handled
                // as a separate line item / PO amendment, not silently
                // absorbed here — this keeps ordered_base_qty a reliable
                // upper bound for reporting.
                $this->assertWithinOutstanding($item, $incomingQty, 'receive', $item->id);

                $variant = ProductVariant::with('currentPrice')->lockForUpdate()->findOrFail($item->product_variant_id);

                StockMovement::create([
                    'product_variant_id' => $item->product_variant_id,
                    'warehouse_id' => $po->warehouse_id,
                    'type' => StockMovementType::Purchase,
                    'quantity' => $incomingQty,
                    'unit_name_used' => $item->ordered_unit_name,
                    'unit_ratio_used' => $item->ordered_unit_ratio,
                    'reference_type' => PurchaseOrder::class,
                    'reference_id' => (string) $po->id,
                    'reference_code' => $po->reference_code,
                    'created_by' => auth()->id(),
                ]);

                // A4: opt-in cost-price update, one new is_current row per
                // line item, only if the PO's unit_cost_price differs from
                // the variant's existing current cost.
                if ($po->update_cost_price) {
                    $currentCost = $variant->currentPrice?->cost_price;

                    if ($currentCost === null || bccomp((string) $currentCost, (string) $item->unit_cost_price, 4) !== 0) {
                        ProductVariantPrice::where('product_variant_id', $variant->id)
                            ->where('is_current', true)
                            ->update(['is_current' => false]);

                        ProductVariantPrice::create([
                            'product_variant_id' => $variant->id,
                            'cost_price' => $item->unit_cost_price,
                            'sale_price' => $variant->currentPrice?->sale_price ?? '0.0000',
                            'effective_from' => now(),
                            'is_current' => true,
                            'set_by' => auth()->id(),
                            'notes' => "Auto-updated from PO {$po->reference_code}",
                        ]);
                    }
                }

                $item->update(['received_base_qty' => $item->received_base_qty + $incomingQty]);
            }

            $allReceived = $po->items()
                ->whereColumn('received_base_qty', '<', 'ordered_base_qty')
                ->doesntExist();

            $po->update([
                'status' => $allReceived ? PurchaseOrderStatus::Completed : PurchaseOrderStatus::PartiallyReceived,
                'received_by' => auth()->id(),
                'received_at' => $allReceived ? now() : $po->received_at,
            ]);
        });
    }

    public function cancelPurchaseOrder(PurchaseOrder $po): void
    {
        // Mirrors [FIX v11] Principle #14: no reversal pathway needed
        // because cancellation is only legal before any stock has moved.
        if ($po->items()->where('received_base_qty', '>', 0)->exists()) {
            throw new Exception(
                'Cannot cancel a purchase order that has already received stock. '.
                'Use a return/adjustment instead.'
            );
        }

        $po->update([
            'status' => PurchaseOrderStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}
