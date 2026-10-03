<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InTransitStatus;
use App\Enums\StockMovementType;
use App\Exceptions\DomainRuleViolationException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidDocumentStateException;
use App\Exceptions\OutstandingQuantityExceededException;
use App\Models\DirectTransfer;
use App\Models\InTransit;
use App\Models\LossLedger;
use App\Models\ProductVariant;
use App\Models\ProductVariantUnitConversion;
use App\Models\StockMovement;
use App\Models\StockMovementIdempotencyKey;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * InventoryService — the transactional inventory engine.
 *
 * Blueprint §6.2. The sole writer of `stock_movements`, paired direct
 * transfers, and dispatch/receive/scan-to-receive flows. Every public
 * method runs inside a database transaction with pessimistic row
 * locking on `product_variants`, `warehouses`, and (for requisition
 * flows) the parent document.
 *
 * Locking discipline (Principle 3): locks are acquired in a consistent
 * order — parent document, then items by id, then variants by id, then
 * warehouses by id — to avoid deadlocks across concurrent dispatches.
 *
 * Public methods:
 *   - recordMovement()    — restricted signed-movement writer
 *   - directTransfer()    — multi-line fire-and-forget transfer (A11)
 *   - dispatchTransfer()  — requisition dispatch, materializes in_transits
 *   - scanToReceive()     — idempotent multi-batch intake (§6.2 / §19.6)
 *   - recordLoss()        — operator-supplied loss against one item
 *   - adjustment()        — signed manual adjustment (source-of-truth write)
 *
 * Private helpers:
 *   - writeOffOmittedItem()      — first-scan omitted cargo (§6.2)
 *   - markInTransit()            — InTransit terminal-state transition
 *   - updateReceiptClosingStatus() — requisition close-out resolution
 */
class InventoryService
{
    /**
     * Record a signed stock movement inside a lock.
     *
     * Restricted: purchase/sale/sale_return/purchase_return movements must
     * go through PurchaseService / SalesService so their guards apply, and
     * adjustment movements must go through `adjustment()` so the
     * caller-supplied sign is preserved — `Adjustment` is intentionally not
     * positive per `StockMovementType::isPositive()`, so routing it here
     * would silently force every adjustment negative.
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
            StockMovementType::Adjustment,
        ], true)) {
            throw new DomainRuleViolationException('errors.invalid_movement_type', [
                'type' => $type->value,
            ]);
        }

        if ($unitRatio < 1) {
            throw new DomainRuleViolationException('errors.invalid_unit_ratio', [
                'ratio' => $unitRatio,
            ]);
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
            throw new DomainRuleViolationException('errors.same_warehouse_transfer', [
                'warehouse' => $fromWarehouseId,
            ]);
        }

        if (empty($items)) {
            throw new DomainRuleViolationException('errors.empty_transfer_items');
        }

        foreach ($items as $index => $item) {
            foreach (['product_variant_id', 'unit_name', 'unit_ratio', 'qty'] as $key) {
                if (! array_key_exists($key, $item)) {
                    throw new DomainRuleViolationException('errors.missing_item_field', [
                        'index' => $index,
                        'field' => $key,
                    ]);
                }
            }
            if ((int) $item['unit_ratio'] < 1) {
                throw new DomainRuleViolationException('errors.invalid_unit_ratio', [
                    'index' => $index,
                    'ratio' => (int) $item['unit_ratio'],
                ]);
            }
            if ((int) $item['qty'] < 1) {
                throw new DomainRuleViolationException('errors.invalid_item_quantity', [
                    'index' => $index,
                    'qty' => (int) $item['qty'],
                ]);
            }
        }

        // A11 — a transfer contains one or more distinct variants: reject
        // duplicate `product_variant_id` entries (mirrors
        // `disableOptionsWhenSelectedInSiblingRepeaterItems()` in §7C.1).
        $seenVariantIds = [];
        foreach ($items as $index => $item) {
            $variantId = (int) $item['product_variant_id'];
            if (in_array($variantId, $seenVariantIds, true)) {
                throw new DomainRuleViolationException('errors.duplicate_transfer_variant', [
                    'index' => $index,
                    'variant' => $variantId,
                ]);
            }
            $seenVariantIds[] = $variantId;
        }

        return DB::transaction(function () use (
            $fromWarehouseId, $toWarehouseId, $items, $referenceCode, $notes
        ) {
            // §20.3 — service independently verifies both warehouse endpoints
            // are within the actor's operational scope. Admins hold global
            // scope with a possibly empty `user_warehouse` pivot (same
            // privilege boundary as the form selects in §7C.1 and the list
            // query in §7C.6), so the membership check applies to non-admin
            // actors. Auditors are read-only (`DirectTransferPolicy::create()`
            // denies them) and are denied here even when assigned to the
            // warehouses.
            $actor = auth()->user();
            if (! $actor) {
                throw new DomainRuleViolationException('errors.warehouse_out_of_scope', [
                    'from' => $fromWarehouseId,
                    'to' => $toWarehouseId,
                ]);
            }
            if (! $actor->isAdmin()) {
                $allowed = $actor->warehouses()->pluck('id')->all();
                if ($actor->isAuditor()
                    || ! in_array($fromWarehouseId, $allowed, true)
                    || ! in_array($toWarehouseId, $allowed, true)) {
                    throw new DomainRuleViolationException('errors.warehouse_out_of_scope', [
                        'from' => $fromWarehouseId,
                        'to' => $toWarehouseId,
                    ]);
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
                    throw new DomainRuleViolationException('errors.unknown_variant', [
                        'variant' => $vid,
                    ]);
                }
            }

            // Unit verification — the caller-supplied `unit_name` /
            // `unit_ratio` pair must match the variant's own conversion row
            // (same rule as `NegotiationService::submitRevision()`); the
            // ratio is resolved server-side, never trusted from the caller.
            foreach ($items as $index => $item) {
                $storedRatio = ProductVariantUnitConversion::where(
                    'product_variant_id',
                    (int) $item['product_variant_id']
                )
                    ->where('unit_name', (string) $item['unit_name'])
                    ->value('base_unit_ratio');

                if ($storedRatio === null) {
                    throw new DomainRuleViolationException('errors.undefined_unit', [
                        'index' => $index,
                        'unit' => (string) $item['unit_name'],
                        'variant' => (int) $item['product_variant_id'],
                    ]);
                }

                if ((int) $storedRatio !== (int) $item['unit_ratio']) {
                    throw new DomainRuleViolationException('errors.unit_ratio_mismatch', [
                        'index' => $index,
                        'unit' => (string) $item['unit_name'],
                    ]);
                }
            }

            // Availability gate — a direct transfer must never drive the
            // source warehouse negative. Required base qty per variant is
            // summed across lines, then checked against batched availability
            // (on-hand minus transfer and sales reservations) at the source.
            $requiredByVariant = [];
            foreach ($items as $item) {
                $vid = (int) $item['product_variant_id'];
                $requiredByVariant[$vid] = ($requiredByVariant[$vid] ?? 0)
                    + ((int) $item['qty'] * (int) $item['unit_ratio']);
            }

            $availableByVariant = ProductVariant::batchAvailableQuantity(
                array_keys($requiredByVariant),
                $fromWarehouseId,
            );

            foreach ($requiredByVariant as $vid => $required) {
                if (($availableByVariant[$vid] ?? 0) < $required) {
                    throw new InsufficientStockException(
                        variantId: $vid,
                        warehouseId: $fromWarehouseId,
                        requested: $required,
                        available: $availableByVariant[$vid] ?? 0,
                    );
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
                throw new InvalidDocumentStateException(
                    documentType: TransferRequisition::class,
                    documentId: (int) $fresh->id,
                    actualStatus: $fresh->status->value,
                    action: 'dispatch',
                );
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

            $reorderPoints = ProductVariant::whereIn('id', $variantIds)->pluck('reorder_point', 'id');

            foreach ($items as $item) {
                if ($item->approved_base_qty === null) {
                    throw new DomainRuleViolationException('errors.missing_approved_quantity', [
                        'item' => (int) $item->id,
                    ]);
                }

                // Never ship more than the outstanding shipped balance
                // (approved − already shipped) for this line: the guard
                // receives this dispatch's quantity (`approved_base_qty`),
                // bounded by `outstandingShippedBaseQty()` per §6.1.
                app(GuardsOutstandingQuantity::class)->assertTransferNotOverShipped(
                    $item,
                    (int) $item->approved_base_qty,
                );

                $variantId = $item->actualVariantId();
                $available = $availableByVariant[$variantId] ?? 0;

                if ($item->approved_base_qty > $available) {
                    throw new InsufficientStockException(
                        variantId: $variantId,
                        warehouseId: $fresh->from_warehouse_id,
                        requested: (int) $item->approved_base_qty,
                        available: $available,
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

                $item->update(['shipped_base_qty' => (int) $item->shipped_base_qty + (int) $item->approved_base_qty]);

                $remaining = $available - $item->approved_base_qty;
                $availableByVariant[$variantId] = $remaining;

                $reorderPoint = $reorderPoints[$variantId] ?? 0;
                if ($available >= $reorderPoint && $remaining < $reorderPoint) {
                    event(new \App\Events\InventoryBelowReorderPoint($variantId, $fresh->from_warehouse_id));
                }
            }

            $fresh->update([
                'status' => \App\Enums\TransferRequisitionStatus::Dispatched,
                'dispatched_at' => now(),
                'dispatched_by' => auth()->id(),
            ]);

            event(new \App\Events\TransferDispatched($fresh->id));
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
            $fresh = TransferRequisition::lockForUpdate()->findOrFail($requisition->id);

            // State guard — intake is only valid once stock has left the
            // source warehouse. Mirrors the `StnController::scan()` (§21.3)
            // boundary so direct service calls cannot receive a
            // Draft/Confirmed requisition.
            if (! in_array($fresh->status, [
                \App\Enums\TransferRequisitionStatus::Dispatched,
                \App\Enums\TransferRequisitionStatus::PartiallyReceived,
            ], true)) {
                throw new InvalidDocumentStateException(
                    documentType: TransferRequisition::class,
                    documentId: (int) $fresh->id,
                    actualStatus: $fresh->status->value,
                    action: 'receive',
                );
            }

            $items = TransferRequisitionItem::where('transfer_requisition_id', $fresh->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $knownIds = $items->pluck('id')->all();

            // §19.6 — the over-outstanding check must occur before canonical
            // sorting, JSON encoding, and hashing. Validate per-item received
            // quantities against outstanding approved qty before computing
            // the checksum/idempotency key.
            foreach ($items as $item) {
                $payload = $scanPayload[$item->id] ?? [
                    'received_good' => 0,
                    'received_damaged' => 0,
                ];
                $good = (int) ($payload['received_good'] ?? 0);
                $damaged = (int) ($payload['received_damaged'] ?? 0);

                if (! is_numeric($payload['received_good']) || ! is_numeric($payload['received_damaged'])
                    || (int) $payload['received_good'] !== $payload['received_good']
                    || (int) $payload['received_damaged'] !== $payload['received_damaged']) {
                    throw new DomainRuleViolationException('errors.non_integer_payload', [
                        'item' => (int) $item->id,
                    ]);
                }

                if ($good < 0 || $damaged < 0) {
                    throw new DomainRuleViolationException('errors.negative_payload', [
                        'item' => (int) $item->id,
                    ]);
                }

                $outstanding = max(0, (int) $item->approved_base_qty - (int) $item->received_good_base_qty - (int) $item->received_damaged_base_qty);

                if ($good + $damaged > $outstanding) {
                    throw new OutstandingQuantityExceededException(
                        itemType: TransferRequisitionItem::class,
                        itemId: (int) $item->id,
                        attempted: $good + $damaged,
                        outstanding: $outstanding,
                    );
                }
            }

            $normalized = [];
            foreach ($scanPayload as $itemId => $quantities) {
                $good = $quantities['received_good'] ?? 0;
                $damaged = $quantities['received_damaged'] ?? 0;

                if (! is_numeric($good) || ! is_numeric($damaged)
                    || (int) $good !== $good || (int) $damaged !== $damaged) {
                    throw new DomainRuleViolationException('errors.non_integer_payload', [
                        'item' => (int) $itemId,
                    ]);
                }

                $good = (int) $good;
                $damaged = (int) $damaged;

                if ($good < 0 || $damaged < 0) {
                    throw new DomainRuleViolationException('errors.negative_payload', [
                        'item' => (int) $itemId,
                    ]);
                }

                $normalized[(int) $itemId] = [
                    'received_damaged' => $damaged,
                    'received_good' => $good,
                ];
            }

            ksort($normalized, SORT_NUMERIC);
            $checksum = hash('sha256', json_encode($normalized));

            $alreadyProcessed = StockMovementIdempotencyKey::where('transfer_requisition_id', $fresh->id)
                ->where('payload_checksum', $checksum)
                ->exists();

            if ($alreadyProcessed) {
                return;
            }

            // First-scan detection: no idempotency record exists yet.
            // Resolved BEFORE the key claim below — the claim itself writes
            // a row, so detection must precede it.
            $isFirstScan = ! StockMovementIdempotencyKey::where(
                'transfer_requisition_id',
                $fresh->id
            )->exists();

            // §19.8 — the unique database constraint
            // (`idempotency_requisition_checksum_unique`) is the final
            // authority. Claim the key BEFORE any ledger write: the loser of
            // a concurrent-duplicate race fails here while this transaction
            // holds no stock-movement or loss-ledger side effects, re-reads
            // the winner's key, and no-ops instead of surfacing a database
            // error. Any other query failure is rethrown.
            try {
                $idempotencyKey = StockMovementIdempotencyKey::create([
                    'transfer_requisition_id' => $fresh->id,
                    'payload_checksum' => $checksum,
                    'resulting_item_states' => [],
                ]);
            } catch (QueryException $e) {
                $winner = StockMovementIdempotencyKey::where('transfer_requisition_id', $fresh->id)
                    ->where('payload_checksum', $checksum)
                    ->first();

                if ($winner !== null) {
                    return;
                }

                throw $e;
            }

            $actualVariantIds = $items->map(fn ($item) => $item->actualVariantId())
                ->unique()
                ->sort()
                ->values()
                ->all();

            if (! empty($actualVariantIds)) {
                ProductVariant::whereIn('id', $actualVariantIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
            }

            Warehouse::lockForUpdate()->findOrFail($fresh->to_warehouse_id);

            $scanPayload = $normalized;

            foreach ($items as $item) {
                $payload = $scanPayload[$item->id] ?? null;

                if ($payload === null && $isFirstScan) {
                    $this->writeOffOmittedItem($fresh, $item);

                    continue;
                }

                if ($payload === null) {
                    continue;
                }

                $good = (int) ($payload['received_good'] ?? 0);
                $damaged = (int) ($payload['received_damaged'] ?? 0);

                $outstanding = max(0, (int) $item->approved_base_qty - (int) $item->received_good_base_qty - (int) $item->received_damaged_base_qty);

                if ($good + $damaged > $outstanding) {
                    throw new OutstandingQuantityExceededException(
                        itemType: TransferRequisitionItem::class,
                        itemId: (int) $item->id,
                        attempted: $good + $damaged,
                        outstanding: $outstanding,
                    );
                }

                if ($good > 0) {
                    StockMovement::create([
                        'product_variant_id' => $item->actualVariantId(),
                        'warehouse_id' => $fresh->to_warehouse_id,
                        'type' => StockMovementType::TransferIn,
                        'quantity' => abs($good),
                        'unit_name_used' => $item->approved_unit_name,
                        'unit_ratio_used' => $item->approved_unit_ratio,
                        'reference_type' => TransferRequisition::class,
                        'reference_id' => (string) $fresh->id,
                        'reference_code' => $fresh->reference_code,
                        'created_by' => auth()->id(),
                    ]);
                }

                $item->update([
                    'received_good_base_qty' => $item->received_good_base_qty + $good,
                    'received_damaged_base_qty' => $item->received_damaged_base_qty + $damaged,
                    'received_qty' => $item->received_qty + $good + $damaged,
                ]);

                $item->refresh();

                if ($item->received_good_base_qty + $item->received_damaged_base_qty >= $item->approved_base_qty) {
                    $this->markInTransit($item, InTransitStatus::Cleared);
                }
            }

            $idempotencyKey->update([
                'resulting_item_states' => $fresh->items()->get()->toArray(),
            ]);

            $this->updateReceiptClosingStatus($fresh);

            // `updateReceiptClosingStatus()` leaves an incompletely
            // accounted requisition in `PartiallyReceived`: the canonical
            // notification text ("Transfer :reference has been received.")
            // describes a completed intake, so only dispatch once fully
            // accounted (`Completed` / `ClosedWithLoss`). There is no
            // separate partial-receipt event.
            if ($fresh->fresh()->status !== \App\Enums\TransferRequisitionStatus::PartiallyReceived) {
                event(new \App\Events\TransferReceived($fresh->id));
            }
        });
    }

    /**
     * Record a loss against one requisition item.
     *
     * Service boundary for the `recordLoss` table action (§7B.3): locks
     * the requisition, item, and effective variant, snapshots cost, writes
     * the LossLedger, accrues damaged qty into item receipt state
     * (mirroring `scanToReceive()` damaged accounting), and transitions
     * InTransit rows — `Cleared` when good + damaged receipts cover the
     * approved qty (mirroring `scanToReceive()`), `Lost` when the
     * shortfall write-off covers the remainder (mirroring
     * `writeOffOmittedItem()`). Partial losses leave InTransit rows
     * untouched. Finally resolves the parent requisition through
     * `updateReceiptClosingStatus()` (`ClosedWithLoss` when fully
     * accounted with losses, `Completed` when fully accounted without
     * losses, otherwise `PartiallyReceived`).
     */
    public function recordLoss(
        TransferRequisition $requisition,
        TransferRequisitionItem $item,
        int $lostBaseQty,
        int $damagedBaseQty,
        string $lossCategory,
        ?string $notes = null,
    ): LossLedger {
        return DB::transaction(function () use (
            $requisition, $item, $lostBaseQty, $damagedBaseQty, $lossCategory, $notes
        ) {
            $fresh = TransferRequisition::lockForUpdate()->findOrFail($requisition->id);

            // State guard — losses are only recorded against in-flight
            // intake. Mirrors the `StnController::scan()` (§21.3) boundary so
            // direct service calls cannot write losses on a
            // Draft/Confirmed requisition.
            if (! in_array($fresh->status, [
                \App\Enums\TransferRequisitionStatus::Dispatched,
                \App\Enums\TransferRequisitionStatus::PartiallyReceived,
            ], true)) {
                throw new InvalidDocumentStateException(
                    documentType: TransferRequisition::class,
                    documentId: (int) $fresh->id,
                    actualStatus: $fresh->status->value,
                    action: 'record_loss',
                );
            }

            $freshItem = TransferRequisitionItem::where('transfer_requisition_id', $fresh->id)
                ->lockForUpdate()
                ->findOrFail($item->id);

            $variant = ProductVariant::query()
                ->lockForUpdate()
                ->findOrFail($freshItem->actualVariantId());

            if ($lostBaseQty < 0 || $damagedBaseQty < 0) {
                throw new DomainRuleViolationException('errors.negative_payload', [
                    'item' => (int) $freshItem->id,
                ]);
            }

            if ($lostBaseQty + $damagedBaseQty < 1) {
                throw new DomainRuleViolationException('errors.empty_loss', [
                    'item' => (int) $freshItem->id,
                ]);
            }

            // Outstanding accounts for prior shortfall write-offs
            // (`loss_ledgers.lost_base_qty`, mirroring
            // `updateReceiptClosingStatus()`), so repeated loss entries
            // cannot be accepted against the same remaining quantity.
            // Damaged quantities need no separate subtraction: `recordLoss()`
            // accrues them into `received_damaged_base_qty` below.
            $recordedLostBaseQty = (int) LossLedger::where(
                'transfer_requisition_item_id',
                $freshItem->id
            )->sum('lost_base_qty');

            $outstanding = max(0, (int) $freshItem->approved_base_qty - (int) $freshItem->received_good_base_qty - (int) $freshItem->received_damaged_base_qty - $recordedLostBaseQty);

            if ($lostBaseQty + $damagedBaseQty > $outstanding) {
                throw new OutstandingQuantityExceededException(
                    itemType: TransferRequisitionItem::class,
                    itemId: (int) $freshItem->id,
                    attempted: $lostBaseQty + $damagedBaseQty,
                    outstanding: $outstanding,
                );
            }

            // Lock the warehouse before the loss-ledger write to ensure
            // uniform pessimistic locking across every multi-warehouse-touching
            // service method (Principle 3). Lock `to_warehouse_id` since the
            // loss ledger records against it.
            Warehouse::lockForUpdate()->findOrFail($fresh->to_warehouse_id);

            $unitCost = LossLedger::snapshotUnitCostFrom($variant);
            $totalLoss = LossLedger::calculateTotalFinancialLoss(
                $unitCost,
                $lostBaseQty + $damagedBaseQty
            );

            $loss = LossLedger::create([
                'transfer_requisition_id' => $fresh->id,
                'transfer_requisition_item_id' => $freshItem->id,
                'product_variant_id' => $variant->id,
                'warehouse_id' => $fresh->to_warehouse_id,
                'lost_base_qty' => $lostBaseQty,
                'damaged_base_qty' => $damagedBaseQty,
                'unit_cost_price' => $unitCost,
                'total_financial_loss' => $totalLoss,
                'loss_category' => $lossCategory,
                'notes' => $notes,
                'recorded_by' => auth()->id(),
                'recorded_at' => now(),
            ]);

            if ($damagedBaseQty > 0) {
                $freshItem->update([
                    'received_damaged_base_qty' => $freshItem->received_damaged_base_qty + $damagedBaseQty,
                    'received_qty' => $freshItem->received_qty + $damagedBaseQty,
                ]);
                $freshItem->refresh();
            }

            $received = (int) $freshItem->received_good_base_qty + (int) $freshItem->received_damaged_base_qty;

            if ($received >= (int) $freshItem->approved_base_qty) {
                $this->markInTransit($freshItem, InTransitStatus::Cleared);
            } elseif ($lostBaseQty >= max(0, (int) $freshItem->approved_base_qty - $received)) {
                $this->markInTransit($freshItem, InTransitStatus::Lost);
            }

            $this->updateReceiptClosingStatus($fresh);

            event(new \App\Events\LossRecorded($loss->id));

            return $loss;
        });
    }

    public function adjustment(
        int $productVariantId,
        int $warehouseId,
        int $signedBaseQuantity,
        string $notes,
    ): StockMovement {
        return DB::transaction(function () use ($productVariantId, $warehouseId, $signedBaseQuantity, $notes) {
            // §20.3 — the service is the security boundary: re-verify the
            // warehouse endpoint against the actor's operational scope,
            // mirroring `directTransfer()`. Admins hold global scope with a
            // possibly empty `user_warehouse` pivot and skip the membership
            // check. Auditors are read-only (ProductVariantPolicy::adjustStock)
            // and are denied here even when assigned to the warehouse.
            $actor = auth()->user();
            if (! $actor) {
                throw new DomainRuleViolationException('errors.warehouse_out_of_scope', [
                    'from' => $warehouseId,
                    'to' => $warehouseId,
                ]);
            }
            if (! $actor->isAdmin()) {
                $allowed = $actor->warehouses()->pluck('id')->all();
                if ($actor->isAuditor() || ! in_array($warehouseId, $allowed, true)) {
                    throw new DomainRuleViolationException('errors.warehouse_out_of_scope', [
                        'from' => $warehouseId,
                        'to' => $warehouseId,
                    ]);
                }
            }

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

        $loss = LossLedger::create([
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
                ? __('resources.loss_ledgers.notes.cost_missing')
                : null,
            'recorded_by' => auth()->id(),
            'recorded_at' => now(),
        ]);

        event(new \App\Events\LossRecorded($loss->id));

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

    /**
     * Terminal-state resolution shared by `scanToReceive()` and `recordLoss()`.
     *
     * An item is fully accounted when good + damaged receipts plus recorded
     * shortfall losses (`loss_ledgers.lost_base_qty` — written by
     * `writeOffOmittedItem()` and `recordLoss()`) cover its approved qty.
     * When every item is fully accounted the requisition closes:
     * `ClosedWithLoss` if any loss ledger exists for the requisition,
     * otherwise `Completed`. Anything less stays `PartiallyReceived`.
     */
    private function updateReceiptClosingStatus(TransferRequisition $requisition): void
    {
        $lostByItem = LossLedger::query()
            ->where('transfer_requisition_id', $requisition->id)
            ->selectRaw('transfer_requisition_item_id, SUM(lost_base_qty) as total')
            ->groupBy('transfer_requisition_item_id')
            ->pluck('total', 'transfer_requisition_item_id');

        $allAccounted = $requisition->items()->get()->every(
            fn ($item) => (int) $item->received_good_base_qty
                + (int) $item->received_damaged_base_qty
                + (int) ($lostByItem[$item->id] ?? 0)
                >= (int) $item->approved_base_qty
        );

        if (! $allAccounted) {
            $requisition->update([
                'status' => \App\Enums\TransferRequisitionStatus::PartiallyReceived,
                'received_by' => auth()->id(),
                'completed_at' => null,
            ]);

            return;
        }

        $hasLoss = LossLedger::where('transfer_requisition_id', $requisition->id)->exists();

        $requisition->update([
            'status' => $hasLoss
                ? \App\Enums\TransferRequisitionStatus::ClosedWithLoss
                : \App\Enums\TransferRequisitionStatus::Completed,
            'received_by' => auth()->id(),
            'completed_at' => now(),
        ]);
    }
}
