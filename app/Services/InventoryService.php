<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InTransitStatus;
use App\Enums\MovementType;
use App\Enums\TransferRequisitionStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\InTransit;
use App\Models\LossLedger;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\TransferRequisition;
use App\Models\User;
use App\Models\WarehouseStock;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class InventoryService
{
    public function lockStockForProduct(int $productId, int $warehouseId): void
    {
        $variantIds = $this->variantIds($productId);

        foreach ($variantIds as $variantId) {
            try {
                WarehouseStock::where('variant_id', $variantId)
                    ->where('warehouse_id', $warehouseId)
                    ->lockForUpdate()
                    ->firstOrCreate(
                        ['variant_id' => $variantId, 'warehouse_id' => $warehouseId],
                        ['on_hand_quantity' => 0, 'reserved_quantity' => 0]
                    );
            } catch (QueryException $e) {
                WarehouseStock::where('variant_id', $variantId)
                    ->where('warehouse_id', $warehouseId)
                    ->lockForUpdate()
                    ->firstOrFail();
            }
        }
    }

    public function currentQuantity(int $productId, int $warehouseId): int
    {
        return WarehouseStock::where('warehouse_id', $warehouseId)
            ->whereIn('variant_id', $this->variantIds($productId))
            ->sum('on_hand_quantity');
    }

    public function availableForNegotiation(int $productId, int $warehouseId): int
    {
        $stocks = WarehouseStock::where('warehouse_id', $warehouseId)
            ->whereIn('variant_id', $this->variantIds($productId))
            ->get();

        if ($stocks->isEmpty()) {
            return 0;
        }

        return $stocks->sum(fn (WarehouseStock $stock) => $stock->on_hand_quantity - $stock->reserved_quantity);
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
        ?string $notes = null,
    ): StockMovement {
        return DB::transaction(function () use (
            $variantId, $warehouseId, $type, $baseQuantity,
            $unitName, $unitRatio, $referenceType, $referenceId,
            $referenceCode, $relatedMovementId, $reference, $notes
        ) {
            try {
                $stock = WarehouseStock::firstOrCreate(
                    ['variant_id' => $variantId, 'warehouse_id' => $warehouseId],
                    ['on_hand_quantity' => 0, 'reserved_quantity' => 0]
                );
            } catch (QueryException $e) {
                $stock = WarehouseStock::where('variant_id', $variantId)
                    ->where('warehouse_id', $warehouseId)
                    ->firstOrFail();
            }

            $stockRow = WarehouseStock::where('id', $stock->id)->lockForUpdate()->first();

            if ($baseQuantity < 0 && ($stockRow->on_hand_quantity + $baseQuantity) < 0) {
                throw new InsufficientStockException(
                    productId: $variantId,
                    warehouseId: $warehouseId,
                    requested: abs($baseQuantity),
                    available: $stockRow->on_hand_quantity,
                );
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
                'notes' => $notes,
                'created_by' => auth()->id(),
            ]);
        });
    }

    public function totalQuantity(int $productId): int
    {
        return WarehouseStock::whereIn('variant_id', $this->variantIds($productId))
            ->sum('on_hand_quantity');
    }

    public function ship(int $productId, int $warehouseId, int $quantity, ?string $reference = null): StockMovement
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Ship quantity must be positive.');
        }

        foreach ($this->variantIds($productId) as $variantId) {
            $stock = WarehouseStock::where('variant_id', $variantId)
                ->where('warehouse_id', $warehouseId)
                ->first();

            if ($stock && $stock->on_hand_quantity >= $quantity) {
                return $this->recordMovement(
                    variantId: $variantId,
                    warehouseId: $warehouseId,
                    type: MovementType::Ship,
                    baseQuantity: -$quantity,
                    referenceCode: $reference,
                );
            }
        }

        throw new InsufficientStockException(
            productId: $productId,
            warehouseId: $warehouseId,
            requested: $quantity,
            available: 0,
        );
    }

    /**
     * @return array{0: StockMovement, 1: StockMovement}
     */
    public function transfer(
        int $productId,
        int $fromWarehouseId,
        int $toWarehouseId,
        int $quantity,
        ?string $reference = null,
    ): array {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Transfer quantity must be positive.');
        }

        if ($fromWarehouseId === $toWarehouseId) {
            throw new InvalidArgumentException('Cannot transfer to the same warehouse.');
        }

        $variantIds = $this->variantIds($productId);

        $outMovement = null;
        $inMovement = null;

        foreach ($variantIds as $variantId) {
            $outMovement = $this->recordMovement(
                variantId: $variantId,
                warehouseId: $fromWarehouseId,
                type: MovementType::TransferOut,
                baseQuantity: -$quantity,
                referenceCode: $reference,
            );

            $inMovement = $this->recordMovement(
                variantId: $variantId,
                warehouseId: $toWarehouseId,
                type: MovementType::TransferIn,
                baseQuantity: $quantity,
                referenceCode: $reference,
                relatedMovementId: $outMovement->id,
            );
        }

        return [$outMovement, $inMovement];
    }

    public function lockStockForRequisition(int $requisitionId, ?User $user = null): void
    {
        DB::transaction(function () use ($requisitionId, $user) {
            $requisition = TransferRequisition::with('items')->findOrFail($requisitionId);
            $auditUser = $user ?? Auth::user();

            foreach ($requisition->items as $item) {
                // Guardrail 5: Validate against negative quantities
                $neededBaseQty = $item->approved_base_qty ?? $item->requested_base_qty;
                if ($neededBaseQty <= 0) {
                    throw new Exception('Requisition item quantity must be strictly greater than zero.');
                }

                $stock = WarehouseStock::where('variant_id', $item->variant_id)
                    ->where('warehouse_id', $requisition->from_warehouse_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (($stock->on_hand_quantity - $stock->reserved_quantity) < $neededBaseQty) {
                    throw new Exception("Insufficient unreserved stock for variant {$item->variant_id}.");
                }

                $stock->reserved_quantity += $neededBaseQty;
                $stock->save();
            }

            $oldStatus = $requisition->status;
            $requisition->update(['status' => TransferRequisitionStatus::Confirmed]);

            if ($auditUser) {
                app(AuditService::class)->recordRequisition(
                    requisition: $requisition,
                    user: $auditUser,
                    action: 'confirmed',
                    changes: ['status' => ['old' => $oldStatus->value, 'new' => TransferRequisitionStatus::Confirmed->value]]
                );
            }
        });
    }

    public function dispatchTransfer(int $requisitionId, ?User $user = null): void
    {
        DB::transaction(function () use ($requisitionId, $user) {
            $requisition = TransferRequisition::with('items')->findOrFail($requisitionId);
            $auditUser = $user ?? Auth::user();

            if ($requisition->status !== TransferRequisitionStatus::Confirmed) {
                throw new Exception('Requisition must be confirmed before dispatch.');
            }

            foreach ($requisition->items as $item) {
                // Guardrail 4: Resolve substitute variant ID
                $actualVariantId = $item->substitute_variant_id ?? $item->variant_id;

                // Guardrail 5: Validate against negative quantities
                $dispatchQty = $item->approved_base_qty ?? $item->requested_base_qty;
                if ($dispatchQty <= 0) {
                    throw new Exception('Requisition item quantity must be strictly greater than zero.');
                }

                $stock = WarehouseStock::where('variant_id', $actualVariantId)
                    ->where('warehouse_id', $requisition->from_warehouse_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $stock->reserved_quantity -= $dispatchQty;
                $stock->on_hand_quantity -= $dispatchQty;
                $stock->save();

                StockMovement::create([
                    'variant_id' => $actualVariantId,
                    'warehouse_id' => $requisition->from_warehouse_id,
                    'type' => MovementType::TransitOut,
                    'quantity' => -$dispatchQty,
                    'unit_name_used' => $item->approved_unit_name ?? $item->requested_unit_name,
                    'unit_ratio_used' => $item->approved_unit_ratio ?? $item->requested_unit_ratio,
                    'reference_type' => TransferRequisition::class,
                    'reference_id' => $requisition->id,
                    'reference_code' => $requisition->reference_code,
                    'created_by' => Auth::id(),
                ]);

                InTransit::create([
                    'requisition_id' => $requisition->id,
                    'requisition_item_id' => $item->id,
                    'variant_id' => $actualVariantId,
                    'dispatched_base_qty' => $dispatchQty,
                    'dispatched_at' => now(),
                    'status' => InTransitStatus::InTransit,
                ]);

                $item->update(['shipped_base_qty' => $dispatchQty]);
            }

            $oldStatus = $requisition->status;
            $requisition->update([
                'status' => TransferRequisitionStatus::Dispatched,
                'dispatched_by' => Auth::id(),
                'dispatched_at' => now(),
            ]);

            if ($auditUser) {
                app(AuditService::class)->recordRequisition(
                    requisition: $requisition,
                    user: $auditUser,
                    action: 'dispatched',
                    changes: ['status' => ['old' => $oldStatus->value, 'new' => TransferRequisitionStatus::Dispatched->value]]
                );
            }
        });
    }

    public function scanToReceive(int $requisitionId, array $receivedItemsData, ?int $userId = null, ?User $user = null): void
    {
        DB::transaction(function () use ($requisitionId, $receivedItemsData, $userId, $user) {
            $requisition = TransferRequisition::with('items')->findOrFail($requisitionId);

            $hasLossOrDamage = false;

            foreach ($requisition->items as $item) {
                // Guardrail 4: Resolve substitute variant ID
                $actualVariantId = $item->substitute_variant_id ?? $item->variant_id;

                // Guardrail 6: Handle omitted items - do not skip, treat as zero received
                if (! isset($receivedItemsData[$item->id])) {
                    // Item was not scanned - treat as received zero quantity
                    $goodBase = 0;
                    $damagedBase = 0;
                } else {
                    $entry = $receivedItemsData[$item->id];
                    $ratio = $item->approved_unit_ratio ?? $item->requested_unit_ratio;

                    $goodBase = $entry['good_qty'] * $ratio;
                    $damagedBase = $entry['damaged_qty'] * $ratio;
                }

                $expectedBase = $item->shipped_base_qty;
                $lostBase = $expectedBase - ($goodBase + $damagedBase);

                if ($goodBase > 0) {
                    $this->recordMovement(
                        variantId: $actualVariantId,
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
                        'variant_id' => $item->variant_id, // Note: keeping original variant for loss ledger
                        'warehouse_id' => $requisition->to_warehouse_id,
                        'lost_base_qty' => max(0, $lostBase),
                        'damaged_base_qty' => $damagedBase,
                        'unit_cost_price' => $variant->cost_price,
                        'total_financial_loss' => (max(0, $lostBase) + $damagedBase) * $variant->cost_price,
                        'loss_category' => $entry['loss_category'] ?? 'Transit Variance',
                        'recorded_by' => $userId ?? Auth::id(),
                        'recorded_at' => now(),
                    ]);
                }

                $item->update([
                    'received_good_base_qty' => $goodBase,
                    'received_damaged_base_qty' => $damagedBase,
                ]);

                InTransit::where('requisition_item_id', $item->id)->update([
                    'received_base_qty' => $goodBase + $damagedBase,
                    'received_at' => now(),
                    'status' => InTransitStatus::Received,
                ]);
            }

            $newStatus = $hasLossOrDamage ? TransferRequisitionStatus::ClosedWithLoss : TransferRequisitionStatus::Completed;
            $oldStatus = $requisition->status;

            $requisition->update([
                'status' => $newStatus,
                'received_by' => $userId ?? Auth::id(),
                'received_at' => now(),
            ]);

            $auditUser = $user ?? Auth::user();
            if ($auditUser) {
                app(AuditService::class)->recordRequisition(
                    requisition: $requisition,
                    user: $auditUser,
                    action: 'received',
                    changes: ['status' => ['old' => $oldStatus->value, 'new' => $newStatus->value]]
                );
            }
        });
    }

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function variantIds(int $productId): \Illuminate\Support\Collection
    {
        return ProductVariant::where('product_id', $productId)->pluck('id');
    }
}
