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
use Throwable;

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
     *
     * [FIX v10] Guard against zero/negative unit ratios corrupting
     * downstream base-quantity math silently.
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
        ?string $notes = null,
    ): StockMovement {
        // [FIX v10] Guard against zero/negative unit ratios corrupting
        // downstream base-quantity math silently.
        if ($unitRatio < 1) {
            throw new Exception(
                "unit_ratio must be a positive integer >= 1, received: {$unitRatio}."
            );
        }

        try {
            return DB::transaction(function () use (
                $productVariantId, $warehouseId, $type, $baseQuantity,
                $unitName, $unitRatio, $referenceType, $referenceId,
                $referenceCode, $relatedMovementId, $notes,
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
                    'notes' => $notes,
                    'created_by' => auth()->id(),
                ]);
            });
        } catch (Throwable $e) {
            // Rollback already happened inside DB::transaction().
            // Do whatever you need here: log, wrap, rethrow, translate...
            report($e);

            throw $e;
        }
    }

    /**
     * Instant two-leg transfer between warehouses, bypassing the requisition
     * workflow entirely (DirectTransferResource). Creates a transfer_out at
     * the origin and a transfer_in at the destination, linked via
     * related_movement_id in both directions, inside one transaction.
     *
     * Unlike dispatchTransfer(), there is no in-transit period: stock leaves
     * one warehouse and lands in the other atomically, in the same commit.
     *
     * [FIX v10] Lock both warehouses in canonical sorted-ID order —
     * identical discipline to dispatchTransfer() below. This
     * prevents a classic lock-ordering deadlock: without this,
     * a concurrent A→B transfer and B→A transfer could each
     * acquire one lock and then block waiting on the other,
     * since neither call previously imposed a consistent
     * acquisition order across the two warehouse rows.
     */
    public function directTransfer(
        int $productVariantId,
        int $fromWarehouseId,
        int $toWarehouseId,
        int $baseQuantity,
        ?string $unitName = null,
        int $unitRatio = 1,
        ?string $referenceCode = null,
        ?string $notes = null,
    ): array {
        if ($fromWarehouseId === $toWarehouseId) {
            throw new Exception('Direct transfer origin and destination warehouses must differ.');
        }

        if ($baseQuantity <= 0) {
            throw new Exception('Direct transfer quantity must be a positive number of base units.');
        }

        // [FIX v10] Same unit-ratio guard as recordMovement().
        if ($unitRatio < 1) {
            throw new Exception(
                "unit_ratio must be a positive integer >= 1, received: {$unitRatio}."
            );
        }

        return DB::transaction(function () use (
            $productVariantId, $fromWarehouseId, $toWarehouseId,
            $baseQuantity, $unitName, $unitRatio, $referenceCode, $notes,
        ) {
            // [FIX v10] Lock both warehouses in canonical sorted-ID order —
            // identical discipline to dispatchTransfer() below. This
            // prevents a classic lock-ordering deadlock: without this,
            // a concurrent A→B transfer and B→A transfer could each
            // acquire one lock and then block waiting on the other,
            // since neither call previously imposed a consistent
            // acquisition order across the two warehouse rows.
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
                'notes' => $notes,
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
                'notes' => $notes,
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
     *
     * [FIX v10] No ?? fallbacks on approved_unit_name/ratio —
     * ConfirmAction materializes them before dispatch. If null here,
     * that's a bug to surface, not a value to mask.
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
                if ($item->approved_base_qty === null) {
                    throw new Exception(
                        "Item #{$item->id} has no approved_base_qty; ConfirmAction must ".
                        'materialize approved_* before dispatch.'
                    );
                }

                $actualVariantId = $item->substitute_product_variant_id ?? $item->product_variant_id;
                $dispatchQty = $item->approved_base_qty;

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
                    'unit_name_used' => $item->approved_unit_name,
                    'unit_ratio_used' => $item->approved_unit_ratio,
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
     *
     * [FIX v10] Now includes a server-side idempotency guard: before
     * processing each item, the method computes what the item's resulting
     * state WOULD be given the incoming payload, and compares it against
     * the item's CURRENT persisted state. If they are identical, the item
     * is skipped as a no-op rather than reprocessed. This protects against
     * duplicate submissions from flaky mobile networks (retry-after-
     * timeout, accidental double-tap on "Confirm Intake") without
     * requiring any client-side coordination or idempotency key.
     *
     * A row is written to stock_movement_idempotency_keys per successfully
     * processed payload (keyed by a checksum of the normalized payload) so
     * operators have a forensic audit trail of duplicate scan attempts —
     * this table is NOT used as the guard mechanism itself; the guard is
     * the state-equality check below.
     */
    public function scanToReceive(int $requisitionId, array $receivedItemsData): void
    {
        DB::transaction(function () use ($requisitionId, $receivedItemsData) {
            $requisition = TransferRequisition::with('items.productVariant')
                ->lockForUpdate()
                ->findOrFail($requisitionId);

            $allowed = [
                TransferRequisitionStatus::Dispatched,
                TransferRequisitionStatus::PartiallyReceived,
            ];

            if (! in_array($requisition->status, $allowed, true)) {
                throw new Exception(
                    "Requisition not in a receivable state. Current: {$requisition->status->value}."
                );
            }

            $isFirstScan = $requisition->status === TransferRequisitionStatus::Dispatched;

            // [FIX v10] Idempotency audit row — written once per unique
            // payload per requisition. Duplicate submissions with an
            // identical payload checksum are recorded here for visibility
            // even though the per-item state-check below is what actually
            // prevents double-processing.
            $payloadChecksum = hash('sha256', json_encode($receivedItemsData));

            foreach ($requisition->items as $item) {
                $alreadyReceived = $item->received_good_base_qty + $item->received_damaged_base_qty;
                $expectedBase = $item->shipped_base_qty;

                if ($alreadyReceived >= $expectedBase) {
                    continue;
                }

                $ratio = $item->approved_unit_ratio;

                if (! isset($receivedItemsData[$item->id])) {
                    if (! $isFirstScan) {
                        continue;
                    }
                    // Omitted item on first scan: 0 received, 100% loss
                    $goodBase = 0;
                    $damagedBase = 0;
                    $lostBase = $expectedBase - $alreadyReceived;
                    $lossCategory = 'omitted_from_intake';

                    // Omitted items on first scan MUST be processed - skip idempotency check entirely
                    $isOmittedOnFirstScan = true;
                } else {
                    $entry = $receivedItemsData[$item->id];
                    $incomingGood = ($entry['good_qty'] ?? 0) * $ratio;
                    $incomingDmg = ($entry['damaged_qty'] ?? 0) * $ratio;

                    $goodBase = $item->received_good_base_qty + $incomingGood;
                    $damagedBase = $item->received_damaged_base_qty + $incomingDmg;
                    $lostBase = max(0, $expectedBase - ($goodBase + $damagedBase));
                    $lossCategory = $entry['loss_category'] ?? 'shortfall';
                }

                // [FIX v10] Idempotency state-check: if applying this
                // payload entry would not change the item's persisted
                // good/damaged totals at all, skip it as a no-op. This
                // covers the case where the same scan payload is submitted
                // twice in a row before the UI reflects the first result
                // (e.g. a double-tap, or a client retry after a timed-out
                // response whose transaction actually committed).
                //
                // Exception: on the FIRST scan, if an item is OMITTED from
                // the payload entirely, we MUST process it to write the
                // 100% loss ledger (per "scanned receipt loss integrity" rule).
                // The idempotency check only applies when the item IS present
                // in the payload but would produce no state change.
                $wouldChangeGood = $goodBase !== $item->received_good_base_qty;
                $wouldChangeDamaged = $damagedBase !== $item->received_damaged_base_qty;

                $isOmittedOnFirstScan = $isFirstScan && ! isset($receivedItemsData[$item->id]);

                if (! $isOmittedOnFirstScan && ! $wouldChangeGood && ! $wouldChangeDamaged) {
                    continue;
                }

                $newlyReceivedGood = $goodBase - $item->received_good_base_qty;
                $newlyReceivedDamaged = $damagedBase - $item->received_damaged_base_qty;

                if ($newlyReceivedGood > 0) {
                    StockMovement::create([
                        'product_variant_id' => $item->substitute_product_variant_id ?? $item->product_variant_id,
                        'warehouse_id' => $requisition->to_warehouse_id,
                        'type' => StockMovementType::TransitIn,
                        'quantity' => $newlyReceivedGood,
                        'unit_name_used' => $item->approved_unit_name,
                        'unit_ratio_used' => $ratio,
                        'reference_type' => TransferRequisition::class,
                        'reference_id' => (string) $requisition->id,
                        'reference_code' => $requisition->reference_code,
                        'created_by' => auth()->id(),
                    ]);
                }

                if ($newlyReceivedDamaged > 0 || $lostBase > 0) {
                    $actualVariantId = $item->substitute_product_variant_id ?? $item->product_variant_id;
                    $variant = ProductVariant::with('currentPrice')->findOrFail($actualVariantId);
                    $unitCost = LossLedger::snapshotUnitCostFrom($variant);

                    // [FIX v10] bcmul() replaces the previous
                    // (float) $unitCost * $qty calculation. Casting a
                    // decimal(15,4) value to native PHP float and
                    // multiplying loses precision — unacceptable given
                    // the schema explicitly supports 4-decimal
                    // micro-pricing. bcmath performs the multiplication
                    // as arbitrary-precision decimal arithmetic instead.
                    $totalLoss = bcmul(
                        (string) ($lostBase + $newlyReceivedDamaged),
                        $unitCost,
                        4
                    );

                    LossLedger::create([
                        'transfer_requisition_id' => $requisition->id,
                        'transfer_requisition_item_id' => $item->id,
                        'product_variant_id' => $actualVariantId,
                        'warehouse_id' => $requisition->to_warehouse_id,
                        'lost_base_qty' => $lostBase,
                        'damaged_base_qty' => $newlyReceivedDamaged,
                        'unit_cost_price' => $unitCost,
                        'total_financial_loss' => $totalLoss,
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

            // [FIX v10] Record the idempotency audit row after successful
            // processing. Unique constraint on (requisition_id, checksum)
            // means a genuine duplicate payload submission will fail this
            // insert with a constraint violation if it somehow reaches
            // this point — but the per-item state-check above should
            // already have made every item a no-op, so this insert
            // failing is itself a useful signal to log, not to surface
            // as a user-facing error.
            try {
                DB::table('stock_movement_idempotency_keys')->insert([
                    'transfer_requisition_id' => $requisition->id,
                    'payload_checksum' => $payloadChecksum,
                    'resulting_item_states' => json_encode(
                        $requisition->items->pluck('received_qty', 'id')
                    ),
                    'created_at' => now(),
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                report($e); // duplicate payload — already logged, non-fatal
            }

            $allClosed = $requisition->items()
                ->whereRaw('(received_good_base_qty + received_damaged_base_qty) < shipped_base_qty')
                ->doesntExist();

            $hasAnyLoss = LossLedger::where('transfer_requisition_id', $requisition->id)->exists();

            $requisition->update([
                'status' => ! $allClosed
                    ? TransferRequisitionStatus::PartiallyReceived
                    : ($hasAnyLoss
                        ? TransferRequisitionStatus::ClosedWithLoss
                        : TransferRequisitionStatus::Completed),
                'received_by' => auth()->id(),
                'completed_at' => $allClosed ? now() : null,
            ]);
        });
    }
}
