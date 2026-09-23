<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SalesOrderStatus;
use App\Enums\StockMovementType;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesService
{
    /**
     * Confirm sales order - change status Draft to Confirmed
     * This creates sales reservation (reservedForSalesQuantity)
     */
    public function confirmSalesOrder(SalesOrder $salesOrder): void
    {
        DB::transaction(function () use ($salesOrder) {
            $salesOrder->loadMissing('items.productVariant');

            if ($salesOrder->status !== SalesOrderStatus::Draft) {
                throw ValidationException::withMessages([
                    'status' => 'Only draft sales orders can be confirmed.',
                ]);
            }

            if ($salesOrder->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Cannot confirm sales order with no line items.',
                ]);
            }

            // Check available stock for each item
            foreach ($salesOrder->items as $item) {
                $available = $item->productVariant->availableQuantity($salesOrder->warehouse_id);
                if ($available < $item->base_qty) {
                    throw ValidationException::withMessages([
                        "items.{$item->id}.qty" => "Insufficient available stock {$item->productVariant->sku}. Available: {$available}, Required: {$item->base_qty}",
                    ]);
                }
            }

            // Snapshot sale price at confirm time
            foreach ($salesOrder->items as $item) {
                $currentPrice = $item->productVariant->currentPrice;
                if ($currentPrice) {
                    $item->update([
                        'unit_sale_price_snapshot' => $currentPrice->sale_price,
                    ]);
                }
            }

            $salesOrder->update([
                'status' => SalesOrderStatus::Confirmed,
                'confirmed_at' => now(),
            ]);
        });
    }

    /**
     * Dispatch sales order - process dispatch lines create stock movements
     */
    public function dispatchSale(SalesOrder $salesOrder, array $dispatchLines): void
    {
        DB::transaction(function () use ($salesOrder, $dispatchLines) {
            $salesOrder->loadMissing(['items.productVariant']);

            if (! in_array($salesOrder->status, [
                SalesOrderStatus::Confirmed,
                SalesOrderStatus::PartiallyDispatched,
            ])) {
                throw ValidationException::withMessages([
                    'status' => 'Only confirmed or partially dispatched sales orders can be dispatched.',
                ]);
            }

            foreach ($dispatchLines as $line) {
                $item = $salesOrder->items()->findOrFail($line['item_id']);
                $dispatchedBaseQty = (int) $line['dispatched_base_qty'];
                $unitName = $line['unit_name'];
                $unitRatio = (int) $line['unit_ratio'];

                // Validate dispatch quantity does not exceed ordered quantity
                if ($dispatchedBaseQty > $item->base_qty - $item->dispatched_base_qty) {
                    throw ValidationException::withMessages([
                        "items.{$item->id}.dispatched_base_qty" => "Dispatched quantity cannot exceed ordered quantity ({$item->base_qty} base units).",
                    ]);
                }

                // Check available stock before dispatch
                // Use onHandQuantity because sales reservations were already accounted for at confirm time
                // We only need to ensure physical stock exists to ship
                $onHand = $item->productVariant->onHandQuantity($salesOrder->warehouse_id);
                if ($onHand < $dispatchedBaseQty) {
                    throw ValidationException::withMessages([
                        "items.{$item->id}.dispatched_base_qty" => "Insufficient on-hand stock for {$item->productVariant->sku}. On-hand: {$onHand}, Requested: {$dispatchedBaseQty}",
                    ]);
                }

                $notes = $line['notes'] ?? null;

                StockMovement::create([
                    'product_variant_id' => $item->product_variant_id,
                    'warehouse_id' => $salesOrder->warehouse_id,
                    'type' => StockMovementType::Sale,
                    'quantity' => -$dispatchedBaseQty, // Negative for outbound
                    'unit_name_used' => $unitName,
                    'unit_ratio_used' => $unitRatio,
                    'reference_type' => SalesOrder::class,
                    'reference_id' => $salesOrder->id,
                    'notes' => $notes,
                    'created_by' => auth()->id(),
                ]);

                $newDispatchedBaseQty = $item->dispatched_base_qty + $dispatchedBaseQty;

                $item->update([
                    'dispatched_base_qty' => $newDispatchedBaseQty,
                ]);
            }

            // Check if ALL items in order are fully dispatched (not just those in dispatch lines)
            $allFullyDispatched = $salesOrder->items()
                ->whereColumn('dispatched_base_qty', '<', 'base_qty')
                ->doesntExist();

            $newStatus = $allFullyDispatched
                ? SalesOrderStatus::Dispatched
                : SalesOrderStatus::PartiallyDispatched;

            $salesOrder->update([
                'status' => $newStatus,
                'dispatched_by' => auth()->id(),
                'dispatched_at' => $newStatus === SalesOrderStatus::Dispatched ? now() : $salesOrder->dispatched_at,
            ]);
        });
    }

    /**
     * Record a sales return - create inbound stock movement with SaleReturn type
     */
    public function recordReturn(SalesOrder $salesOrder, array $returnLines): void
    {
        DB::transaction(function () use ($salesOrder, $returnLines) {
            $salesOrder->loadMissing(['items.productVariant']);

            if (! in_array($salesOrder->status, [
                SalesOrderStatus::Dispatched,
                SalesOrderStatus::Completed,
            ])) {
                throw ValidationException::withMessages([
                    'status' => 'Only dispatched or completed sales orders can be returned.',
                ]);
            }

            foreach ($returnLines as $line) {
                $item = $salesOrder->items()->findOrFail($line['item_id']);
                $returnBaseQty = (int) $line['return_base_qty'];
                $unitName = $line['unit_name'];
                $unitRatio = (int) $line['unit_ratio'];

                if ($returnBaseQty > $item->dispatched_base_qty) {
                    throw ValidationException::withMessages([
                        "items.{$item->id}.return_base_qty" => "Return quantity cannot exceed dispatched quantity ({$item->dispatched_base_qty} base units).",
                    ]);
                }

                $notes = $line['notes'] ?? null;

                StockMovement::create([
                    'product_variant_id' => $item->product_variant_id,
                    'warehouse_id' => $salesOrder->warehouse_id,
                    'type' => StockMovementType::SaleReturn,
                    'quantity' => $returnBaseQty, // Positive for inbound return
                    'unit_name_used' => $unitName,
                    'unit_ratio_used' => $unitRatio,
                    'reference_type' => SalesOrder::class,
                    'reference_id' => $salesOrder->id,
                    'notes' => $notes,
                    'created_by' => auth()->id(),
                ]);
            }

            if ($salesOrder->status === SalesOrderStatus::Dispatched) {
                $salesOrder->update([
                    'status' => SalesOrderStatus::Completed,
                ]);
            }
        });
    }

    /**
     * Cancel sales order - only allowed Draft, Confirmed, PartiallyDispatched
     * No reversal stock movements - cancellation only pre-dispatch
     */
    public function cancelSalesOrder(SalesOrder $salesOrder): void
    {
        DB::transaction(function () use ($salesOrder) {
            if (! in_array($salesOrder->status, [
                SalesOrderStatus::Draft,
                SalesOrderStatus::Confirmed,
                SalesOrderStatus::PartiallyDispatched,
            ])) {
                throw ValidationException::withMessages([
                    'status' => 'This sales order cannot cancelled.',
                ]);
            }

            if ($salesOrder->status !== SalesOrderStatus::Draft) {
                $dispatchedItems = $salesOrder->items()->where('dispatched_base_qty', '>', 0)->exists();
                if ($dispatchedItems) {
                    throw ValidationException::withMessages([
                        'status' => 'Cannot cancel sales order dispatched items. Use returns instead.',
                    ]);
                }
            }

            $salesOrder->update([
                'status' => SalesOrderStatus::Cancelled,
                'cancelled_at' => now(),
            ]);
        });
    }
}
