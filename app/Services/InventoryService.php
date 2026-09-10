<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InTransitStatus;
use App\Enums\StockMovementType;
use App\Enums\TransferRequisitionStatus;
use App\Models\InTransit;
use App\Models\LossLedger;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\TransferRequisition;
use App\Models\Warehouse;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * Transactional stock engine. Physical stock is never stored directly — it is
 * always the signed SUM of stock_movements rows (see ProductVariant::onHandQuantity()).
 * Every write path here runs inside DB::transaction() with lockForUpdate() on
 * the ProductVariant and/or TransferRequisition rows involved, per the
 * blueprint's pessimistic-locking principle.
 */
class InventoryService
{
    /**
     * Record a single signed stock movement for a variant at a warehouse,
     * after verifying it won't drive on-hand stock negative.
     */
    public function recordMovement(
        int $productVariantId,
        int $warehouseId,
        StockMovementType $type,
        int $baseQuantity,
        ?string $unitName = null,
        int $unitRatio = 1,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $referenceCode = null,
        ?int $relatedMovementId = null,
    ): StockMovement {
        return DB::transaction(function () use (
            $productVariantId, $warehouseId, $type, $baseQuantity,
            $unitName, $unitRatio, $referenceType, $referenceId,
            $referenceCode, $relatedMovementId,
        ) {
            $variant = ProductVariant::lockForUpdate()->findOrFail($productVariantId);
            $currentStock = $variant->onHandQuantity($warehouseId);

            if ($baseQuantity < 0 && ($currentStock + $baseQuantity) < 0) {
                throw new Exception(
                    "Insufficient stock for SKU {$variant->sku} at warehouse ID {$warehouseId}. ".
                    "Available: {$currentStock}, requested deduction: ".abs($baseQuantity).'.'
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
     * Instant two-leg transfer between warehouses, bypassing the requisition
     * workflow entirely (DirectTransferResource). Creates a transfer_out at
     * the origin and a transfer_in at the destination, linked via
     * related_movement_id in both directions, inside one transaction.
     *
     * Unlike dispatchTransfer(), there is no in-transit period: stock leaves
     * one warehouse and lands in the other atomically, in the same commit.
     */
    public function directTransfer(
        int $productVariantId,
        int $fromWarehouseId,
        int $toWarehouseId,
        int $baseQuantity,
        ?string $unitName = null,
        int $unitRatio = 1,
        ?string $referenceCode = null,
    ): array {
        if ($fromWarehouseId === $toWarehouseId) {
            throw new Exception('Direct transfer origin and destination warehouses must differ.');
        }

        if ($baseQuantity <= 0) {
            throw new Exception('Direct transfer quantity must be a positive number of base units.');
        }

        return DB::transaction(function () use (
            $productVariantId, $fromWarehouseId, $toWarehouseId,
            $baseQuantity, $unitName, $unitRatio, $referenceCode,
        ) {
            // Lock both warehouse rows in a deterministic order (by ID) to avoid
            // deadlocking against a concurrent reverse-direction direct transfer
            // between the same two warehouses.
            $warehouseIds = collect([$fromWarehouseId, $toWarehouseId])->sort()->values();
            Warehouse::whereIn('id', $warehouseIds)->lockForUpdate()->get();

            $variant = ProductVariant::lockForUpdate()->findOrFail($productVariantId);
            $currentStock = $variant->onHandQuantity($fromWarehouseId);

            if ($currentStock < $baseQuantity) {
                throw new Exception(
                    "Insufficient stock for SKU {$variant->sku} at origin warehouse ID {$fromWarehouseId}. ".
                    "Available: {$currentStock}, requested: {$baseQuantity}."
                );
            }

            $resolvedUnitName = $unitName ?? $variant->base_unit_name;

            $outMovement = StockMovement::create([
                'product_variant_id' => $productVariantId,
                'warehouse_id' => $fromWarehouseId,
                'type' => StockMovementType::TransferOut,
                'quantity' => -$baseQuantity,
                'unit_name_used' => $resolvedUnitName,
                'unit_ratio_used' => $unitRatio,
                'reference_code' => $referenceCode,
                'created_by' => auth()->id(),
            ]);

            $inMovement = StockMovement::create([
                'product_variant_id' => $productVariantId,
                'warehouse_id' => $toWarehouseId,
                'type' => StockMovementType::TransferIn,
                'quantity' => $baseQuantity,
                'unit_name_used' => $resolvedUnitName,
                'unit_ratio_used' => $unitRatio,
                'related_movement_id' => $outMovement->id,
                'reference_code' => $referenceCode,
                'created_by' => auth()->id(),
            ]);

            $outMovement->update(['related_movement_id' => $inMovement->id]);

            return [$outMovement->fresh(), $inMovement];
        });
    }

    /**
     * Dispatch a confirmed requisition: for each item, resolve the actual
     * variant (substitute if negotiated), verify stock, debit the origin
     * warehouse with a transit_out movement, and open an in_transits row.
     *
     * approved_base_qty is populated exclusively by an accepted negotiation
     * revision (see TransferRequisitionItemRevision::accept() /
     * NegotiationService::accept()). If no negotiation occurred, it falls
     * back to requested_base_qty — an item that was never negotiated ships
     * exactly what was originally requested.
     */
    public function dispatchTransfer(int $requisitionId): void
    {
        DB::transaction(function () use ($requisitionId) {
            $requisition = TransferRequisition::with('items.productVariant')
                ->lockForUpdate()
                ->findOrFail($requisitionId);

            if ($requisition->status !== TransferRequisitionStatus::Confirmed) {
                throw new Exception(
                    "Requisition must be confirmed before dispatch. Current status: {$requisition->status->value}."
                );
            }

            foreach ($requisition->items as $item) {
                $actualVariantId = $item->substitute_product_variant_id ?? $item->product_variant_id;
                $dispatchQty = $item->approved_base_qty ?? $item->requested_base_qty;

                $variant = ProductVariant::lockForUpdate()->findOrFail($actualVariantId);

                if ($variant->onHandQuantity($requisition->from_warehouse_id) < $dispatchQty) {
                    throw new Exception(
                        "Insufficient stock for SKU {$variant->sku} at origin warehouse for ".
                        "requisition {$requisition->reference_code}."
                    );
                }

                StockMovement::create([
                    'product_variant_id' => $actualVariantId,
                    'warehouse_id' => $requisition->from_warehouse_id,
                    'type' => StockMovementType::TransitOut,
                    'quantity' => -$dispatchQty,
                    'unit_name_used' => $item->approved_unit_name ?? $item->requested_unit_name,
                    'unit_ratio_used' => $item->approved_unit_ratio ?? $item->requested_unit_ratio,
                    'reference_type' => TransferRequisition::class,
                    'reference_id' => (string) $requisition->id,
                    'reference_code' => $requisition->reference_code,
                    'created_by' => auth()->id(),
                ]);

                InTransit::create([
                    'transfer_requisition_id' => $requisition->id,
                    'transfer_requisition_item_id' => $item->id,
                    'product_variant_id' => $actualVariantId,
                    'dispatched_base_qty' => $dispatchQty,
                    'dispatched_at' => now(),
                    'status' => InTransitStatus::InTransit,
                ]);

                $item->update(['shipped_base_qty' => $dispatchQty]);
            }

            $requisition->update([
                'status' => TransferRequisitionStatus::Dispatched,
                'dispatched_by' => auth()->id(),
                'dispatched_at' => now(),
            ]);
        });
    }

    /**
     * Reconcile a scan-to-receive intake against what was dispatched. Any
     * dispatched item absent from $receivedItemsData is treated as a total
     * loss (0 received) rather than silently skipped — this is the "scanned
     * receipt loss integrity" rule: omitted cargo is not forgiven, it is
     * written off at the variant's current cost price.
     *
     * $receivedItemsData is keyed by transfer_requisition_item_id, each value
     * shaped as ['good_qty' => int, 'damaged_qty' => int, 'loss_category' => ?string]
     * where good_qty/damaged_qty are in the item's approved (or requested)
     * packaging unit — NOT base units; this method converts using the ratio
     * that was actually shipped.
     */
    public function scanToReceive(int $requisitionId, array $receivedItemsData): void
    {
        DB::transaction(function () use ($requisitionId, $receivedItemsData) {
            $requisition = TransferRequisition::with('items.productVariant')
                ->lockForUpdate()
                ->findOrFail($requisitionId);

            if ($requisition->status !== TransferRequisitionStatus::Dispatched) {
                throw new Exception(
                    "Requisition must be in dispatched state to receive. Current status: {$requisition->status->value}."
                );
            }

            $hasLossOrDamage = false;

            foreach ($requisition->items as $item) {
                $actualVariantId = $item->substitute_product_variant_id ?? $item->product_variant_id;
                $expectedBase = $item->shipped_base_qty;
                $ratio = $item->approved_unit_ratio ?? $item->requested_unit_ratio;

                if (! isset($receivedItemsData[$item->id])) {
                    $goodBase = 0;
                    $damagedBase = 0;
                    $lostBase = $expectedBase;
                    $lossCategory = 'omitted_from_intake';
                } else {
                    $entry = $receivedItemsData[$item->id];
                    $goodBase = ($entry['good_qty'] ?? 0) * $ratio;
                    $damagedBase = ($entry['damaged_qty'] ?? 0) * $ratio;
                    $lostBase = max(0, $expectedBase - ($goodBase + $damagedBase));
                    $lossCategory = $entry['loss_category'] ?? 'shortfall';
                }

                if ($goodBase > 0) {
                    StockMovement::create([
                        'product_variant_id' => $actualVariantId,
                        'warehouse_id' => $requisition->to_warehouse_id,
                        'type' => StockMovementType::TransitIn,
                        'quantity' => $goodBase,
                        'unit_name_used' => $item->approved_unit_name ?? $item->requested_unit_name,
                        'unit_ratio_used' => $ratio,
                        'reference_type' => TransferRequisition::class,
                        'reference_id' => (string) $requisition->id,
                        'reference_code' => $requisition->reference_code,
                        'created_by' => auth()->id(),
                    ]);
                }

                if ($damagedBase > 0 || $lostBase > 0) {
                    $hasLossOrDamage = true;

                    $variant = ProductVariant::with('currentPrice')->findOrFail($actualVariantId);
                    $unitCost = LossLedger::snapshotUnitCostFrom($variant) ?? '0.0000';

                    LossLedger::create([
                        'transfer_requisition_id' => $requisition->id,
                        'transfer_requisition_item_id' => $item->id,
                        'product_variant_id' => $actualVariantId,
                        'warehouse_id' => $requisition->to_warehouse_id,
                        'lost_base_qty' => $lostBase,
                        'damaged_base_qty' => $damagedBase,
                        'unit_cost_price' => $unitCost,
                        'total_financial_loss' => ($lostBase + $damagedBase) * (float) $unitCost,
                        'loss_category' => $lossCategory,
                        'recorded_by' => auth()->id(),
                        'recorded_at' => now(),
                    ]);
                }

                $item->update([
                    'received_good_base_qty' => $goodBase,
                    'received_damaged_base_qty' => $damagedBase,
                    'received_qty' => $goodBase + $damagedBase,
                ]);

                InTransit::where('transfer_requisition_item_id', $item->id)
                    ->update(['status' => InTransitStatus::Cleared]);
            }

            $requisition->update([
                'status' => $hasLossOrDamage
                    ? TransferRequisitionStatus::ClosedWithLoss
                    : TransferRequisitionStatus::Completed,
                'received_by' => auth()->id(),
                'completed_at' => now(),
            ]);
        });
    }
}
