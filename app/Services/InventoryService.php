<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InTransitStatus;
use App\Enums\StockMovementType;
use App\Models\DirectTransfer;
use App\Models\InTransit;
use App\Models\LossLedger;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\StockMovementIdempotencyKey;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use App\Models\Warehouse;
use DomainException;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function __construct(
        private readonly GuardsOutstandingQuantity $guards,
    ) {}

    /**
     * Record a signed stock movement inside a lock.
     *
     * Restricted: purchase/sale/sale_return/purchase_return movements must
     * go through PurchaseService / SalesService so their guards apply.
     */
    public function recordMovement(
        int $productVariantId,
        int $warehouseId,
        StockMovementType $type,
        int $baseQuantity,
        string $unitName,
        int $unitRatio,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $referenceCode = null,
        ?string $notes = null,
    ): StockMovement {
        if (in_array($type, [
            StockMovementType::Purchase,
            StockMovementType::Sale,
            StockMovementType::SaleReturn,
            StockMovementType::PurchaseReturn,
        ], true)) {
            throw new DomainException(
                'Use dedicated service methods for purchase/sale movements.'
            );
        }

        if ($unitRatio < 1) {
            throw new DomainException('Unit ratio must be >= 1.');
        }

        return DB::transaction(function () use (
            $productVariantId, $warehouseId, $type, $baseQuantity,
            $unitName, $unitRatio, $referenceType, $referenceId, $referenceCode, $notes
        ) {
            ProductVariant::lockForUpdate()->findOrFail($productVariantId);
            Warehouse::lockForUpdate()->findOrFail($warehouseId);

            return StockMovement::create([
                'product_variant_id' => $productVariantId,
                'warehouse_id' => $warehouseId,
                'type' => $type,
                'quantity' => $type->isPositive() ? abs($baseQuantity) : -abs($baseQuantity),
                'unit_name_used' => $unitName,
                'unit_ratio_used' => $unitRatio,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reference_code' => $referenceCode,
                'notes' => $notes,
                'created_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Move N distinct variants between two warehouses atomically.
     *
     * Creates one DirectTransfer header, one DirectTransferItem per line, and a
     * paired (TransferOut, TransferIn) StockMovement per line inside a single
     * transaction. Fire-and-forget: no draft/dispatch/receive lifecycle.
     *
     * @param  array<int, array{
     *     product_variant_id: int,
     *     unit_name:          string,
     *     unit_ratio:         int,
     *     qty:                int,
     *     notes?:             ?string,
     * }>  $items
     */
    public function directTransfer(
        int $fromWarehouseId,
        int $toWarehouseId,
        array $items,
        string $referenceCode,
        ?string $notes = null,
    ): DirectTransfer {
        if ($fromWarehouseId === $toWarehouseId) {
            throw new DomainException('Origin and destination warehouses must differ.');
        }

        if (empty($items)) {
            throw new DomainException('A direct transfer must contain at least one item.');
        }

        foreach ($items as $index => $item) {
            foreach (['product_variant_id', 'unit_name', 'unit_ratio', 'qty'] as $key) {
                if (! array_key_exists($key, $item)) {
                    throw new DomainException("Item [{$index}] is missing required field: {$key}.");
                }
            }
            if ((int) $item['unit_ratio'] < 1) {
                throw new DomainException("Item [{$index}] unit ratio must be >= 1.");
            }
            if ((int) $item['qty'] < 1) {
                throw new DomainException("Item [{$index}] quantity must be >= 1.");
            }
        }

        return DB::transaction(function () use (
            $fromWarehouseId, $toWarehouseId, $items, $referenceCode, $notes
        ) {
            // §20.3 — service independently verifies both warehouse endpoints
            // are within the actor's operational scope.
            $actor = auth()->user();
            if ($actor) {
                $allowed = $actor->warehouses()->pluck('id')->all();
                if (! in_array($fromWarehouseId, $allowed, true)
                    || ! in_array($toWarehouseId, $allowed, true)) {
                    throw new DomainException(
                        'Both warehouses must be within your operational scope.'
                    );
                }
            }

            // Lock warehouses in sorted-ID order to avoid deadlocks.
            $warehouseIds = collect([$fromWarehouseId, $toWarehouseId])
                ->sort()->values()->all();
            Warehouse::whereIn('id', $warehouseIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            // Lock every referenced variant in sorted-ID order.
            $variantIds = collect($items)
                ->pluck('product_variant_id')
                ->unique()
                ->sort()
                ->values()
                ->all();
            $lockedVariants = ProductVariant::whereIn('id', $variantIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($variantIds as $vid) {
                if (! $lockedVariants->has($vid)) {
                    throw new DomainException("Product variant [{$vid}] not found.");
                }
            }

            $header = DirectTransfer::create([
                'reference_code' => $referenceCode,
                'from_warehouse_id' => $fromWarehouseId,
                'to_warehouse_id' => $toWarehouseId,
                'notes' => $notes,
                'transferred_by' => $actor?->id,
                'transferred_at' => now(),
            ]);

            foreach ($items as $item) {
                $unitRatio = (int) $item['unit_ratio'];
                $qty = (int) $item['qty'];
                $baseQty = $qty * $unitRatio;

                $line = $header->items()->create([
                    'product_variant_id' => (int) $item['product_variant_id'],
                    'unit_name' => (string) $item['unit_name'],
                    'unit_ratio' => $unitRatio,
                    'qty' => $qty,
                    'base_qty' => $baseQty,
                    'notes' => $item['notes'] ?? null,
                ]);

                $out = StockMovement::create([
                    'product_variant_id' => $line->product_variant_id,
                    'warehouse_id' => $fromWarehouseId,
                    'type' => StockMovementType::TransferOut,
                    'quantity' => -abs($baseQty),
                    'unit_name_used' => $line->unit_name,
                    'unit_ratio_used' => $line->unit_ratio,
                    'reference_type' => DirectTransfer::class,
                    'reference_id' => (string) $header->id,
                    'reference_code' => $referenceCode,
                    'notes' => $notes,
                    'created_by' => $actor?->id,
                ]);

                StockMovement::create([
                    'product_variant_id' => $line->product_variant_id,
                    'warehouse_id' => $toWarehouseId,
                    'type' => StockMovementType::TransferIn,
                    'quantity' => abs($baseQty),
                    'unit_name_used' => $line->unit_name,
                    'unit_ratio_used' => $line->unit_ratio,
                    'related_movement_id' => $out->id,
                    'reference_type' => DirectTransfer::class,
                    'reference_id' => (string) $header->id,
                    'reference_code' => $referenceCode,
                    'notes' => $notes,
                    'created_by' => $actor?->id,
                ]);
            }

            return $header->fresh([
                'items.productVariant',
                'fromWarehouse',
                'toWarehouse',
                'transferredBy',
            ]);
        });
    }

    /**
     * Dispatch a confirmed requisition — materializes in_transits.
     *
     * Re-locks items and variants under the parent transaction and verifies
     * on-hand availability, excluding this requisition's own reservation.
     */
    public function dispatchTransfer(TransferRequisition $requisition): void
    {
        DB::transaction(function () use ($requisition) {
            $fresh = TransferRequisition::lockForUpdate()->findOrFail($requisition->id);

            if ($fresh->status !== \App\Enums\TransferRequisitionStatus::Confirmed) {
                throw new DomainException('Only confirmed requisitions can be dispatched.');
            }

            $items = TransferRequisitionItem::where('transfer_requisition_id', $fresh->id)
                ->lockForUpdate()
                ->get();

            $variantIds = $items->pluck('product_variant_id')
                ->merge($items->pluck('substitute_product_variant_id'))
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (! empty($variantIds)) {
                ProductVariant::whereIn('id', $variantIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
            }

            Warehouse::lockForUpdate()->findOrFail($fresh->from_warehouse_id);

            $availableByVariant = ProductVariant::batchAvailableQuantity(
                $variantIds,
                $fresh->from_warehouse_id,
                null,
                $fresh->id, // exclude own transfer reservation
            );

            foreach ($items as $item) {
                if ($item->approved_base_qty === null) {
                    throw new DomainException(
                        "Item {$item->id} has no approved base quantity."
                    );
                }

                $variantId = $item->actualVariantId();
                $available = $availableByVariant[$variantId] ?? 0;

                if ($item->approved_base_qty > $available) {
                    throw new DomainException(
                        "Insufficient stock to dispatch variant {$variantId}. ".
                        "Required: {$item->approved_base_qty}, available: {$available}."
                    );
                }

                InTransit::create([
                    'transfer_requisition_id' => $fresh->id,
                    'transfer_requisition_item_id' => $item->id,
                    'product_variant_id' => $variantId,
                    'dispatched_base_qty' => $item->approved_base_qty,
                    'dispatched_at' => now(),
                    'status' => InTransitStatus::InTransit,
                ]);

                StockMovement::create([
                    'product_variant_id' => $variantId,
                    'warehouse_id' => $fresh->from_warehouse_id,
                    'type' => StockMovementType::TransferOut,
                    'quantity' => -abs($item->approved_base_qty),
                    'unit_name_used' => $item->approved_unit_name,
                    'unit_ratio_used' => $item->approved_unit_ratio,
                    'reference_type' => TransferRequisition::class,
                    'reference_id' => (string) $fresh->id,
                    'reference_code' => $fresh->reference_code,
                    'created_by' => auth()->id(),
                ]);

                $item->update(['shipped_base_qty' => $item->approved_base_qty]);

                $availableByVariant[$variantId] = $available - $item->approved_base_qty;
            }

            $fresh->update([
                'status' => \App\Enums\TransferRequisitionStatus::Dispatched,
                'dispatched_at' => now(),
                'dispatched_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Scan-to-receive with idempotency via state-equality check.
     *
     * @param  array<int, array{received_good:int, received_damaged:int}>  $scanPayload
     */
    public function scanToReceive(TransferRequisition $requisition, array $scanPayload): void
    {
        DB::transaction(function () use ($requisition, $scanPayload) {
            TransferRequisition::lockForUpdate()->findOrFail($requisition->id);

            $checksum = hash('sha256', json_encode($scanPayload));

            $alreadyProcessed = StockMovementIdempotencyKey::where('transfer_requisition_id', $requisition->id)
                ->where('payload_checksum', $checksum)
                ->exists();

            if ($alreadyProcessed) {
                return;
            }

            // First-scan detection: no idempotency record exists yet.
            $isFirstScan = ! StockMovementIdempotencyKey::where(
                'transfer_requisition_id',
                $requisition->id
            )->exists();

            $items = TransferRequisitionItem::where('transfer_requisition_id', $requisition->id)
                ->lockForUpdate()
                ->get();

            foreach ($items as $item) {
                $payload = $scanPayload[$item->id] ?? null;

                if ($payload === null && $isFirstScan) {
                    $this->writeOffOmittedItem($requisition, $item);

                    continue;
                }

                if ($payload === null) {
                    continue;
                }

                $good = (int) ($payload['received_good'] ?? 0);
                $damaged = (int) ($payload['received_damaged'] ?? 0);

                if ($good > 0) {
                    StockMovement::create([
                        'product_variant_id' => $item->actualVariantId(),
                        'warehouse_id' => $requisition->to_warehouse_id,
                        'type' => StockMovementType::TransferIn,
                        'quantity' => abs($good),
                        'unit_name_used' => $item->approved_unit_name,
                        'unit_ratio_used' => $item->approved_unit_ratio,
                        'reference_type' => TransferRequisition::class,
                        'reference_id' => (string) $requisition->id,
                        'reference_code' => $requisition->reference_code,
                        'created_by' => auth()->id(),
                    ]);
                }

                $item->update([
                    'received_good_base_qty' => $item->received_good_base_qty + $good,
                    'received_damaged_base_qty' => $item->received_damaged_base_qty + $damaged,
                    'received_qty' => $item->received_qty + $good,
                ]);

                $item->refresh();

                if ($item->received_good_base_qty + $item->received_damaged_base_qty >= $item->approved_base_qty) {
                    $this->markInTransit($item, InTransitStatus::Cleared);
                }
            }

            StockMovementIdempotencyKey::create([
                'transfer_requisition_id' => $requisition->id,
                'payload_checksum' => $checksum,
                'resulting_item_states' => $requisition->items()->get()->toArray(),
            ]);

            $allReceived = $requisition->items()->get()->every(
                fn ($item) => $item->received_good_base_qty + $item->received_damaged_base_qty >= $item->approved_base_qty
            );

            $requisition->update([
                'status' => $allReceived
                    ? \App\Enums\TransferRequisitionStatus::Completed
                    : \App\Enums\TransferRequisitionStatus::PartiallyReceived,
                'received_by' => auth()->id(),
                'completed_at' => $allReceived ? now() : null,
            ]);
        });
    }

    public function adjustment(
        int $productVariantId,
        int $warehouseId,
        int $signedBaseQuantity,
        string $notes,
    ): StockMovement {
        return DB::transaction(function () use ($productVariantId, $warehouseId, $signedBaseQuantity, $notes) {
            $variant = ProductVariant::lockForUpdate()->findOrFail($productVariantId);
            Warehouse::lockForUpdate()->findOrFail($warehouseId);

            return StockMovement::create([
                'product_variant_id' => $productVariantId,
                'warehouse_id' => $warehouseId,
                'type' => StockMovementType::Adjustment,
                'quantity' => $signedBaseQuantity,
                'unit_name_used' => $variant->base_unit_name,
                'unit_ratio_used' => 1,
                'notes' => $notes,
                'created_by' => auth()->id(),
            ]);
        });
    }

    private function writeOffOmittedItem(TransferRequisition $requisition, TransferRequisitionItem $item): void
    {
        $actualVariant = ProductVariant::query()
            ->lockForUpdate()
            ->findOrFail($item->actualVariantId());

        $unitCost = LossLedger::snapshotUnitCostFrom($actualVariant);
        $totalLoss = LossLedger::calculateTotalFinancialLoss($unitCost, $item->approved_base_qty);

        LossLedger::create([
            'transfer_requisition_id' => $requisition->id,
            'transfer_requisition_item_id' => $item->id,
            'product_variant_id' => $item->actualVariantId(),
            'warehouse_id' => $requisition->to_warehouse_id,
            'lost_base_qty' => $item->approved_base_qty,
            'damaged_base_qty' => 0,
            'unit_cost_price' => $unitCost,
            'total_financial_loss' => $totalLoss,
            'loss_category' => 'shortfall',
            'notes' => bccomp($unitCost, '0.0000', 4) === 0
                ? 'Cost price missing or zero at time of write-off.'
                : null,
            'recorded_by' => auth()->id(),
            'recorded_at' => now(),
        ]);

        $this->markInTransit($item, InTransitStatus::Lost);
    }

    private function markInTransit(TransferRequisitionItem $item, InTransitStatus $status): void
    {
        InTransit::where('transfer_requisition_item_id', $item->id)
            ->where('status', InTransitStatus::InTransit->value)
            ->update([
                'status' => $status->value,
                'cleared_at' => now(),
            ]);
    }
}
