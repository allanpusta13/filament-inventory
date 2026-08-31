<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InTransitStatus;
use App\Enums\MovementType;
use App\Enums\TransferRequisitionStatus;
use App\Models\InTransit;
use App\Models\LossLedger;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\TransferRequisition;
use App\Models\WarehouseStock;
use Exception;
use Illuminate\Support\Facades\DB;

final class InventoryService
{
    public function lockStockForProduct(int $productId, int $warehouseId): void
    {
        $variantIds = ProductVariant::where('product_id', $productId)->pluck('id');

        foreach ($variantIds as $variantId) {
            WarehouseStock::where('variant_id', $variantId)
                ->where('warehouse_id', $warehouseId)
                ->lockForUpdate()
                ->firstOrCreate(
                    ['variant_id' => $variantId, 'warehouse_id' => $warehouseId],
                    ['on_hand_quantity' => 0, 'reserved_quantity' => 0]
                );
        }
    }

    public function currentQuantity(int $productId, int $warehouseId): int
    {
        $variantIds = ProductVariant::where('product_id', $productId)->pluck('id');

        return WarehouseStock::where('warehouse_id', $warehouseId)
            ->whereIn('variant_id', $variantIds)
            ->sum('on_hand_quantity');
    }

    public function availableForNegotiation(int $productId, int $warehouseId): int
    {
        $variantIds = ProductVariant::where('product_id', $productId)->pluck('id');

        $stock = WarehouseStock::where('warehouse_id', $warehouseId)
            ->whereIn('variant_id', $variantIds)
            ->first();

        if ($stock === null) {
            return 0;
        }

        return $stock->on_hand_quantity - $stock->reserved_quantity;
    }

    public function recordMovement(
        int $variantId,
        int $warehouseId,
        MovementType|string $type,
        int $baseQuantity,
        ?string $unitName = null,
        int $unitRatio = 1,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $referenceCode = null,
        ?int $relatedMovementId = null,
        ?string $reference = null,
    ): StockMovement {
        return DB::transaction(function () use (
            $variantId, $warehouseId, $type, $baseQuantity,
            $unitName, $unitRatio, $referenceType, $referenceId,
            $referenceCode, $relatedMovementId, $reference
        ) {
            $stock = WarehouseStock::firstOrCreate(
                ['variant_id' => $variantId, 'warehouse_id' => $warehouseId],
                ['on_hand_quantity' => 0, 'reserved_quantity' => 0]
            );

            $stockRow = WarehouseStock::where('id', $stock->id)->lockForUpdate()->first();

            if ($baseQuantity < 0 && ($stockRow->on_hand_quantity + $baseQuantity) < 0) {
                throw new Exception("Insufficient stock available for Variant ID {$variantId} at Warehouse ID {$warehouseId}.");
            }

            $stockRow->on_hand_quantity += $baseQuantity;
            $stockRow->save();

            return StockMovement::create([
                'variant_id' => $variantId,
                'warehouse_id' => $warehouseId,
                'type' => $type instanceof MovementType ? $type->value : $type,
                'quantity' => $baseQuantity,
                'unit_name_used' => $unitName ?? 'Base Unit',
                'unit_ratio_used' => $unitRatio,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reference_code' => $referenceCode ?? $reference,
                'related_movement_id' => $relatedMovementId,
                'created_by' => auth()->id(),
            ]);
        });
    }

    public function lockStockForRequisition(int $requisitionId): void
    {
        DB::transaction(function () use ($requisitionId) {
            $requisition = TransferRequisition::with('items')->findOrFail($requisitionId);

            foreach ($requisition->items as $item) {
                $stock = WarehouseStock::where('variant_id', $item->variant_id)
                    ->where('warehouse_id', $requisition->from_warehouse_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $neededBaseQty = $item->approved_base_qty ?? $item->requested_base_qty;

                if (($stock->on_hand_quantity - $stock->reserved_quantity) < $neededBaseQty) {
                    throw new Exception("Insufficient unreserved stock for variant {$item->variant_id}.");
                }

                $stock->reserved_quantity += $neededBaseQty;
                $stock->save();
            }

            $requisition->update(['status' => TransferRequisitionStatus::Confirmed]);
        });
    }

    public function dispatchTransfer(int $requisitionId): void
    {
        DB::transaction(function () use ($requisitionId) {
            $requisition = TransferRequisition::with('items')->findOrFail($requisitionId);

            if ($requisition->status !== TransferRequisitionStatus::Confirmed) {
                throw new Exception('Requisition must be confirmed before dispatch.');
            }

            foreach ($requisition->items as $item) {
                $dispatchQty = $item->approved_base_qty ?? $item->requested_base_qty;

                $stock = WarehouseStock::where('variant_id', $item->variant_id)
                    ->where('warehouse_id', $requisition->from_warehouse_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $stock->reserved_quantity -= $dispatchQty;
                $stock->on_hand_quantity -= $dispatchQty;
                $stock->save();

                StockMovement::create([
                    'variant_id' => $item->variant_id,
                    'warehouse_id' => $requisition->from_warehouse_id,
                    'type' => MovementType::TransitOut,
                    'quantity' => -$dispatchQty,
                    'unit_name_used' => $item->approved_unit_name ?? $item->requested_unit_name,
                    'unit_ratio_used' => $item->approved_unit_ratio ?? $item->requested_unit_ratio,
                    'reference_type' => TransferRequisition::class,
                    'reference_id' => $requisition->id,
                    'reference_code' => $requisition->reference_code,
                    'created_by' => auth()->id(),
                ]);

                InTransit::create([
                    'requisition_id' => $requisition->id,
                    'requisition_item_id' => $item->id,
                    'variant_id' => $item->variant_id,
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
     * @param  array<int, array{good_qty: int, damaged_qty: int, loss_category?: string}>  $receivedItemsData
     */
    public function scanToReceive(int $requisitionId, array $receivedItemsData, ?int $userId = null): void
    {
        DB::transaction(function () use ($requisitionId, $receivedItemsData, $userId) {
            $requisition = TransferRequisition::with('items')->findOrFail($requisitionId);

            $hasLossOrDamage = false;

            foreach ($requisition->items as $item) {
                if (! isset($receivedItemsData[$item->id])) {
                    continue;
                }

                $entry = $receivedItemsData[$item->id];
                $ratio = $item->approved_unit_ratio ?? $item->requested_unit_ratio;

                $goodBase = $entry['good_qty'] * $ratio;
                $damagedBase = $entry['damaged_qty'] * $ratio;
                $expectedBase = $item->shipped_base_qty;

                $lostBase = $expectedBase - ($goodBase + $damagedBase);

                if ($goodBase > 0) {
                    $this->recordMovement(
                        variantId: $item->variant_id,
                        warehouseId: $requisition->to_warehouse_id,
                        type: MovementType::TransitIn,
                        baseQuantity: $goodBase,
                        unitName: $item->approved_unit_name ?? $item->requested_unit_name,
                        unitRatio: $ratio,
                        referenceType: TransferRequisition::class,
                        referenceId: $requisition->id,
                        referenceCode: $requisition->reference_code
                    );
                }

                if ($damagedBase > 0 || $lostBase > 0) {
                    $hasLossOrDamage = true;
                    $variant = ProductVariant::findOrFail($item->variant_id);

                    LossLedger::create([
                        'requisition_id' => $requisition->id,
                        'requisition_item_id' => $item->id,
                        'variant_id' => $item->variant_id,
                        'warehouse_id' => $requisition->to_warehouse_id,
                        'lost_base_qty' => max(0, $lostBase),
                        'damaged_base_qty' => $damagedBase,
                        'unit_cost_price' => $variant->cost_price,
                        'total_financial_loss' => (max(0, $lostBase) + $damagedBase) * $variant->cost_price,
                        'loss_category' => $entry['loss_category'] ?? 'Transit Variance',
                        'recorded_by' => $userId ?? auth()->id(),
                        'recorded_at' => now(),
                    ]);
                }

                $item->update([
                    'received_good_base_qty' => $goodBase,
                    'received_damaged_base_qty' => $damagedBase,
                ]);

                InTransit::where('requisition_item_id', $item->id)->update([
                    'status' => InTransitStatus::Cleared,
                ]);
            }

            $finalStatus = $hasLossOrDamage ? TransferRequisitionStatus::ClosedWithLoss : TransferRequisitionStatus::Completed;

            $requisition->update([
                'status' => $finalStatus,
                'received_by' => $userId ?? auth()->id(),
                'completed_at' => now(),
            ]);
        });
    }
}
