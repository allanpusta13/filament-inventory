<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InTransit;
use App\Models\LossLedger;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\TransferRequisition;
use Exception;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Record an atomic stock movement in the immutable ledger.
     */
    public function recordMovement(
        int $productVariantId,
        int $warehouseId,
        string $type,
        int $baseQuantity,
        ?string $unitName = null,
        int $unitRatio = 1,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $referenceCode = null,
        ?int $relatedMovementId = null
    ): StockMovement {
        if (in_array($type, ['receive', 'transfer_in', 'transit_in', 'adjustment']) && $baseQuantity < 0) {
            throw new Exception("Intake movement quantity must be positive. Provided: {$baseQuantity}");
        }

        return DB::transaction(function () use (
            $productVariantId, $warehouseId, $type, $baseQuantity,
            $unitName, $unitRatio, $referenceType, $referenceId,
            $referenceCode, $relatedMovementId
        ) {
            $variant = ProductVariant::lockForUpdate()->findOrFail($productVariantId);
            $currentStock = $variant->onHandQuantity($warehouseId);

            if ($baseQuantity < 0 && ($currentStock + $baseQuantity) < 0) {
                throw new Exception(
                    "Insufficient stock for SKU {$variant->sku} at Warehouse ID {$warehouseId}. Available: {$currentStock}, Requested deduction: ".abs($baseQuantity)
                );
            }

            return StockMovement::create([
                'product_variant_id' => $productVariantId,
                'warehouse_id' => $warehouseId,
                'type' => $type,
                'quantity' => $baseQuantity,
                'unit_name_used' => $unitName ?? $variant->base_unit_name,
                'unit_ratio_used' => $unitRatio,
                'related_movement_id' => $relatedMovementId,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reference_code' => $referenceCode,
                'created_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Dispatch Requisition: Deduct stock from origin, write transit_out, register Virtual In-Transit.
     */
    public function dispatchTransfer(int $requisitionId): void
    {
        DB::transaction(function () use ($requisitionId) {
            $requisition = TransferRequisition::with('items.productVariant')->lockForUpdate()->findOrFail($requisitionId);

            if ($requisition->status !== 'confirmed') {
                throw new Exception("Requisition must be confirmed before dispatch. Current: {$requisition->status}");
            }

            foreach ($requisition->items as $item) {
                $actualVariantId = $item->substitute_product_variant_id ?? $item->product_variant_id;
                $dispatchQty = $item->approved_base_qty ?? $item->requested_base_qty;

                if ($dispatchQty <= 0) {
                    throw new Exception("Invalid dispatch quantity ({$dispatchQty}) on line item {$item->id}.");
                }

                $variant = ProductVariant::lockForUpdate()->findOrFail($actualVariantId);
                if ($variant->onHandQuantity($requisition->from_warehouse_id) < $dispatchQty) {
                    throw new Exception(
                        "Insufficient unreserved stock for SKU {$variant->sku} at origin warehouse."
                    );
                }

                // Record origin debit movement
                StockMovement::create([
                    'product_variant_id' => $actualVariantId,
                    'warehouse_id' => $requisition->from_warehouse_id,
                    'type' => 'transit_out',
                    'quantity' => -$dispatchQty,
                    'unit_name_used' => $item->approved_unit_name ?? $item->requested_unit_name,
                    'unit_ratio_used' => $item->approved_unit_ratio ?? $item->requested_unit_ratio,
                    'reference_type' => TransferRequisition::class,
                    'reference_id' => $requisition->id,
                    'reference_code' => $requisition->reference_code,
                    'created_by' => auth()->id(),
                ]);

                // Register Virtual In-Transit row
                InTransit::create([
                    'transfer_requisition_id' => $requisition->id,
                    'transfer_requisition_item_id' => $item->id,
                    'product_variant_id' => $actualVariantId,
                    'dispatched_base_qty' => $dispatchQty,
                    'dispatched_at' => now(),
                    'status' => 'in_transit',
                ]);

                $item->update(['shipped_base_qty' => $dispatchQty]);
            }

            $requisition->update([
                'status' => 'dispatched',
                'dispatched_by' => auth()->id(),
                'dispatched_at' => now(),
            ]);
        });
    }

    /**
     * Scan-to-Receive pipeline: Credit destination stock, write loss logs for variances or omitted items.
     */
    public function scanToReceive(int $requisitionId, array $receivedItemsData): void
    {
        DB::transaction(function () use ($requisitionId, $receivedItemsData) {
            $requisition = TransferRequisition::with('items.productVariant')->lockForUpdate()->findOrFail($requisitionId);

            if ($requisition->status !== 'dispatched') {
                throw new Exception("Requisition must be in dispatched state. Current: {$requisition->status}");
            }

            $hasLossOrDamage = false;

            foreach ($requisition->items as $item) {
                $actualVariantId = $item->substitute_product_variant_id ?? $item->product_variant_id;
                $expectedBase = $item->shipped_base_qty;

                // Handle omitted items (Guardrail 6)
                if (! isset($receivedItemsData[$item->id])) {
                    $goodBase = 0;
                    $damagedBase = 0;
                    $lostBase = $expectedBase;
                    $lossCategory = 'Omitted From Intake / Transit Loss';
                } else {
                    $entry = $receivedItemsData[$item->id];
                    $ratio = $item->approved_unit_ratio ?? $item->requested_unit_ratio;
                    $goodBase = ($entry['good_qty'] ?? 0) * $ratio;
                    $damagedBase = ($entry['damaged_qty'] ?? 0) * $ratio;
                    $lostBase = max(0, $expectedBase - ($goodBase + $damagedBase));
                    $lossCategory = $entry['loss_category'] ?? 'Transit Variance';
                }

                if ($goodBase > 0) {
                    $this->recordMovement(
                        productVariantId: $actualVariantId,
                        warehouseId: $requisition->to_warehouse_id,
                        type: 'transit_in',
                        baseQuantity: $goodBase,
                        unitName: $item->approved_unit_name ?? $item->requested_unit_name,
                        unitRatio: $item->approved_unit_ratio ?? $item->requested_unit_ratio,
                        referenceType: TransferRequisition::class,
                        referenceId: $requisition->id,
                        referenceCode: $requisition->reference_code
                    );
                }

                if ($damagedBase > 0 || $lostBase > 0) {
                    $hasLossOrDamage = true;
                    $variant = ProductVariant::findOrFail($actualVariantId);

                    LossLedger::create([
                        'transfer_requisition_id' => $requisition->id,
                        'transfer_requisition_item_id' => $item->id,
                        'product_variant_id' => $actualVariantId,
                        'warehouse_id' => $requisition->to_warehouse_id,
                        'lost_base_qty' => $lostBase,
                        'damaged_base_qty' => $damagedBase,
                        'unit_cost_price' => $variant->cost_price,
                        'total_financial_loss' => ($lostBase + $damagedBase) * $variant->cost_price,
                        'loss_category' => $lossCategory,
                        'recorded_by' => auth()->id(),
                        'recorded_at' => now(),
                    ]);
                }

                $item->update([
                    'received_good_base_qty' => $goodBase,
                    'received_damaged_base_qty' => $damagedBase,
                    'received_qty' => ($goodBase + $damagedBase),
                ]);

                InTransit::where('transfer_requisition_item_id', $item->id)->update([
                    'status' => 'cleared',
                ]);
            }

            $requisition->update([
                'status' => $hasLossOrDamage ? 'closed_with_loss' : 'completed',
                'received_by' => auth()->id(),
                'completed_at' => now(),
            ]);
        });
    }
}
