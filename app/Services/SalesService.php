<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SalesOrderStatus;
use App\Enums\StockMovementType;
use App\Exceptions\DomainRuleViolationException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidDocumentStateException;
use App\Exceptions\OutstandingQuantityExceededException;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

/**
 * SalesService — the sales-order lifecycle boundary.
 *
 * Blueprint §6.5. Sales are a single-entity flow (A2) symmetric to
 * purchases (§6.4), with the lifecycle:
 *
 *   draft → confirmed → partially_dispatched → dispatched / cancelled
 *
 * Public methods:
 *   - confirmSalesOrder()    — Draft → Confirmed; snapshots sale price
 *   - dispatchSale()         — Confirmed | PartiallyDispatched → Dispatched | PartiallyDispatched
 *   - recordSalesReturn()    — cumulative over-return guard, writes SaleReturn
 *   - cancelSalesOrder()     — Draft | Confirmed (no stock moved) → Cancelled
 *
 * Locking discipline (§6.5, Principle 3): every public method locks the
 * parent order, its items (by id), its variants (by id), and the target
 * warehouse before mutating any state.
 *
 * Price snapshot (§19.5): `confirmSalesOrder()` locks the variants
 * before reading `currentPrice.sale_price` and stores it on the item's
 * `unit_sale_price_snapshot`. The column stays at its `0.0000` default
 * until confirm — the pre-confirm dispatch flow is impossible.
 *
 * Self-reservation exclusion (§6.5 / §0 principle 13): `dispatchSale()`
 * passes its own order id into `batchAvailableQuantity()` so the order's
 * own reservation does not count against its own availability.
 *
 * Return pathway (A6): dispatched sales are reversed by a return only —
 * there is no cancel pathway once stock has shipped.
 */
class SalesService
{
    public function __construct(
        private readonly GuardsOutstandingQuantity $guards,
    ) {}

    /**
     * Transition a Draft order to Confirmed, snapshotting the sale price.
     *
     * §6.5 / §19.5: locks the order, its items, and the unique variant
     * set (sorted by id) before reading each variant's current price.
     * Every item's `unit_sale_price_snapshot` is set to the variant's
     * `currentPrice.sale_price` at confirm time.
     */
    public function confirmSalesOrder(SalesOrder $order): void
    {
        DB::transaction(function () use ($order) {
            $fresh = SalesOrder::lockForUpdate()->findOrFail($order->id);

            if ($fresh->status !== SalesOrderStatus::Draft) {
                throw new InvalidDocumentStateException(
                    documentType: SalesOrder::class,
                    documentId: (int) $fresh->id,
                    actualStatus: $fresh->status->value,
                    action: 'confirm',
                );
            }

            $items = SalesOrderItem::where('sales_order_id', $fresh->id)
                ->lockForUpdate()
                ->get();

            // The form requires at least one line item (§7H.1): never
            // confirm an item-less sales order.
            if ($items->isEmpty()) {
                throw new DomainRuleViolationException('errors.empty_sales_items');
            }

            $variantIds = $items->pluck('product_variant_id')->unique()->values()->all();

            $lockedVariants = ProductVariant::whereIn('id', $variantIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->with('currentPrice')
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                $variant = $lockedVariants->get($item->product_variant_id);
                $salePrice = (string) ($variant?->currentPrice?->sale_price ?? '0.0000');
                $item->update(['unit_sale_price_snapshot' => $salePrice]);
            }

            $fresh->update([
                'status' => SalesOrderStatus::Confirmed,
                'confirmed_at' => now(),
            ]);
        });
    }

    /**
     * Dispatch quantities against a Confirmed or PartiallyDispatched order.
     *
     * §6.5: for each item, guard the requested dispatch against the
     * outstanding balance (`GuardsOutstandingQuantity::assertSaleNotOverDispatched()`),
     * verify available stock (excluding this order's own reservation),
     * write a negative-signed `Sale` movement, and increment the item's
     * `dispatched_base_qty`. Then update the order status to `Dispatched`
     * (when every item is fully dispatched) or `PartiallyDispatched`
     * (otherwise).
     *
     * @param  array<int, int>  $dispatchByItemId  item_id => base_qty_dispatched
     */
    public function dispatchSale(int $orderId, array $dispatchByItemId): void
    {
        DB::transaction(function () use ($orderId, $dispatchByItemId) {
            $order = SalesOrder::lockForUpdate()->findOrFail($orderId);

            if (! in_array($order->status, [
                SalesOrderStatus::Confirmed,
                SalesOrderStatus::PartiallyDispatched,
            ], true)) {
                throw new InvalidDocumentStateException(
                    documentType: SalesOrder::class,
                    documentId: (int) $order->id,
                    actualStatus: $order->status->value,
                    action: 'dispatch',
                );
            }

            $items = SalesOrderItem::where('sales_order_id', $order->id)
                ->lockForUpdate()
                ->get();

            $variantIds = $items->pluck('product_variant_id')->unique()->values()->all();

            if (! empty($variantIds)) {
                ProductVariant::whereIn('id', $variantIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
            }

            Warehouse::lockForUpdate()->findOrFail($order->warehouse_id);

            // Exclude this order's own reservation so its outstanding qty
            // does not count against its own availability.
            $availableByVariant = ProductVariant::batchAvailableQuantity(
                $variantIds,
                $order->warehouse_id,
                $order->id,
            );

            $reorderPoints = ProductVariant::whereIn('id', $variantIds)->pluck('reorder_point', 'id');

            // Reject an empty/all-zero dispatch payload: with no movement
            // created, the `every()` completion check below would otherwise
            // still mutate the order status.
            $hasDispatchMovement = false;
            foreach ($items as $item) {
                if ((int) ($dispatchByItemId[$item->id] ?? 0) > 0) {
                    $hasDispatchMovement = true;
                    break;
                }
            }
            if (! $hasDispatchMovement) {
                throw new DomainRuleViolationException('errors.empty_sales_dispatch');
            }

            foreach ($items as $item) {
                $dispatched = (int) ($dispatchByItemId[$item->id] ?? 0);
                if ($dispatched <= 0) {
                    continue;
                }

                $this->guards->assertSaleNotOverDispatched($item, $dispatched);

                $available = $availableByVariant[$item->product_variant_id] ?? 0;
                if ($dispatched > $available) {
                    throw new InsufficientStockException(
                        variantId: $item->product_variant_id,
                        warehouseId: $order->warehouse_id,
                        requested: $dispatched,
                        available: $available,
                    );
                }

                StockMovement::create([
                    'product_variant_id' => $item->product_variant_id,
                    'warehouse_id' => $order->warehouse_id,
                    'type' => StockMovementType::Sale,
                    'quantity' => -abs($dispatched),
                    'unit_name_used' => $item->unit_name,
                    'unit_ratio_used' => $item->unit_ratio,
                    'reference_type' => SalesOrder::class,
                    'reference_id' => (string) $order->id,
                    'reference_code' => $order->reference_code,
                    'created_by' => auth()->id(),
                ]);

                $item->update(['dispatched_base_qty' => $item->dispatched_base_qty + $dispatched]);
                $remaining = $available - $dispatched;
                $availableByVariant[$item->product_variant_id] = $remaining;

                $reorderPoint = $reorderPoints[$item->product_variant_id] ?? 0;
                if ($available >= $reorderPoint && $remaining < $reorderPoint) {
                    event(new \App\Events\InventoryBelowReorderPoint($item->product_variant_id, $order->warehouse_id));
                }
            }

            $allDispatched = $items->every(
                fn ($item) => $item->fresh()->dispatched_base_qty >= $item->base_qty
            );

            $order->update([
                'status' => $allDispatched ? SalesOrderStatus::Dispatched : SalesOrderStatus::PartiallyDispatched,
                'dispatched_at' => $allDispatched ? now() : null,
                'dispatched_by' => auth()->id(),
            ]);

            // The catalogue describes the event as "Sales order :reference
            // has been dispatched.": only dispatch on `Dispatched`, not on
            // `PartiallyDispatched` (there is no separate partial event).
            if ($allDispatched) {
                event(new \App\Events\SalesOrderDispatched($order->id));
            }
        });
    }

    /**
     * Record a sales return with server-side cumulative over-return guard.
     * Locks variant and warehouse to preserve uniform locking discipline.
     */
    public function recordSalesReturn(int $itemId, int $returnedBaseQty, ?string $notes = null): void
    {
        DB::transaction(function () use ($itemId, $returnedBaseQty, $notes) {
            $item = SalesOrderItem::lockForUpdate()->findOrFail($itemId);

            $order = $item->salesOrder;

            ProductVariant::lockForUpdate()->findOrFail($item->product_variant_id);
            Warehouse::lockForUpdate()->findOrFail($order->warehouse_id);

            $alreadyReturned = $item->alreadyReturnedBaseQty();

            // The form requires `returned_base_qty` to be at least 1
            // (§7H.3): reject zero or negative service input instead of
            // normalizing it with `abs()`.
            if ($returnedBaseQty < 1) {
                throw new DomainRuleViolationException('errors.invalid_return_quantity', [
                    'item' => (int) $item->id,
                    'qty' => $returnedBaseQty,
                ]);
            }

            if ($alreadyReturned + $returnedBaseQty > $item->dispatched_base_qty) {
                throw new OutstandingQuantityExceededException(
                    itemType: SalesOrderItem::class,
                    itemId: (int) $item->id,
                    attempted: $alreadyReturned + $returnedBaseQty,
                    outstanding: (int) $item->dispatched_base_qty - (int) $alreadyReturned,
                );
            }

            StockMovement::create([
                'product_variant_id' => $item->product_variant_id,
                'warehouse_id' => $order->warehouse_id,
                'type' => StockMovementType::SaleReturn,
                'quantity' => $returnedBaseQty,
                'unit_name_used' => $item->unit_name,
                'unit_ratio_used' => $item->unit_ratio,
                'reference_type' => SalesOrderItem::class,
                'reference_id' => (string) $item->id,
                'reference_code' => $order->reference_code,
                'notes' => $notes,
                'created_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Cancel a Draft or Confirmed sales order.
     *
     * §6.5: legality is delegated to `SalesOrder::canBeCancelled()`
     * (§3.16), which permits Draft and Confirmed only — cancellation is
     * denied once any stock has moved.
     */
    public function cancelSalesOrder(SalesOrder $order): void
    {
        DB::transaction(function () use ($order) {
            $fresh = SalesOrder::lockForUpdate()->findOrFail($order->id);

            if (! $fresh->canBeCancelled()) {
                throw new InvalidDocumentStateException(
                    documentType: SalesOrder::class,
                    documentId: (int) $fresh->id,
                    actualStatus: $fresh->status->value,
                    action: 'cancel',
                );
            }

            $fresh->update([
                'status' => SalesOrderStatus::Cancelled,
                'cancelled_at' => now(),
            ]);
        });
    }
}
