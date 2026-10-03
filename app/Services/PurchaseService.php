<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMovementType;
use App\Exceptions\DomainRuleViolationException;
use App\Exceptions\InvalidDocumentStateException;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

/**
 * PurchaseService — the purchase-order lifecycle boundary.
 *
 * Blueprint §6.4. Purchases are a single-entity flow (A2) with a
 * lightweight lifecycle:
 *
 *   draft → ordered → partially_received → received / cancelled
 *
 * Public methods:
 *   - orderPurchase()          — Draft → Ordered
 *   - receivePurchase()        — Ordered | PartiallyReceived → Received | PartiallyReceived
 *   - cancelPurchaseOrder()    — Draft | Ordered (no received items) → Cancelled
 *
 * Private helpers:
 *   - updateCurrentCostPrice() — canonical price-writer used when
 *                                `update_cost_price = true`
 *
 * Locking discipline (§6.4, Principle 3): every public method locks the
 * parent order, its items (by id), its variants (by id), and the target
 * warehouse before mutating any state.
 *
 * ⚠ Blueprint-as-written note: `orderPurchase()` fires
 * `PurchaseOrderReceived` (§6.4 line "Dispatch purchase-order-received
 * event on ordering (Principle 19.4)") and `receivePurchase()` fires it
 * again when the order is fully received. This means the event fires
 * twice across a single order's lifecycle. The test suite documents
 * this. If the intent was a distinct "ordered" event, that is a
 * blueprint correction to raise — this implementation faithfully
 * reproduces the blueprint's event behavior.
 */
class PurchaseService
{
    public function __construct(
        private readonly GuardsOutstandingQuantity $guards,
    ) {}

    /**
     * Transition a Draft purchase order to Ordered.
     *
     * §6.4: only Draft → Ordered is legal. The order must have at least
     * one line item.
     */
    public function orderPurchase(PurchaseOrder $order): void
    {
        DB::transaction(function () use ($order) {
            $fresh = PurchaseOrder::lockForUpdate()->findOrFail($order->id);

            if ($fresh->status !== PurchaseOrderStatus::Draft) {
                throw new InvalidDocumentStateException(
                    documentType: PurchaseOrder::class,
                    documentId: (int) $fresh->id,
                    actualStatus: $fresh->status->value,
                    action: 'order',
                );
            }

            // The form requires at least one repeater item (§7G.1):
            // never order an item-less purchase.
            if (! $fresh->items()->exists()) {
                throw new DomainRuleViolationException('errors.empty_purchase_items');
            }

            $fresh->update([
                'status' => PurchaseOrderStatus::Ordered,
                'ordered_at' => now(),
                'ordered_by' => $fresh->ordered_by ?? auth()->id(),
            ]);

            // Dispatch purchase-order-received event on ordering (Principle 19.4)
            event(new \App\Events\PurchaseOrderReceived($order->id));
        });
    }

    /**
     * Receive quantities against an Ordered or PartiallyReceived order.
     *
     * §6.4: for each item, guard the requested receive against the
     * outstanding balance (`GuardsOutstandingQuantity::assertPurchaseNotOverReceived()`),
     * write a `Purchase` movement, and increment the item's
     * `received_base_qty`. Then update the order status to `Received`
     * (when every item is fully received) or `PartiallyReceived`
     * (otherwise). When `update_cost_price = true`, replace the
     * variant's current price with the order's unit cost, preserving
     * the existing sale price.
     *
     * @param  array<int, int>  $receivedByItemId  item_id => base_qty_received
     */
    public function receivePurchase(int $orderId, array $receivedByItemId): void
    {
        DB::transaction(function () use ($orderId, $receivedByItemId) {
            $order = PurchaseOrder::lockForUpdate()->findOrFail($orderId);

            if (! in_array($order->status, [
                PurchaseOrderStatus::Ordered,
                PurchaseOrderStatus::PartiallyReceived,
            ], true)) {
                throw new InvalidDocumentStateException(
                    documentType: PurchaseOrder::class,
                    documentId: (int) $order->id,
                    actualStatus: $order->status->value,
                    action: 'receive',
                );
            }

            $items = PurchaseOrderItem::where('purchase_order_id', $order->id)
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

            // Reject an empty or all-zero receipt payload: with no
            // movement created, `$items->every(...)` below would otherwise
            // still mutate the order status.
            $hasReceiptMovement = false;
            foreach ($items as $item) {
                if ((int) ($receivedByItemId[$item->id] ?? 0) > 0) {
                    $hasReceiptMovement = true;
                    break;
                }
            }
            if (! $hasReceiptMovement) {
                throw new DomainRuleViolationException('errors.empty_purchase_receipt');
            }

            foreach ($items as $item) {
                $received = (int) ($receivedByItemId[$item->id] ?? 0);
                if ($received <= 0) {
                    continue;
                }

                $this->guards->assertPurchaseNotOverReceived($item, $received);

                StockMovement::create([
                    'product_variant_id' => $item->product_variant_id,
                    'warehouse_id' => $order->warehouse_id,
                    'type' => StockMovementType::Purchase,
                    'quantity' => abs($received),
                    'unit_name_used' => $item->ordered_unit_name,
                    'unit_ratio_used' => $item->ordered_unit_ratio,
                    'reference_type' => PurchaseOrder::class,
                    'reference_id' => (string) $order->id,
                    'reference_code' => $order->reference_code,
                    'created_by' => auth()->id(),
                ]);

                $item->update(['received_base_qty' => $item->received_base_qty + $received]);
            }

            $allReceived = $items->every(
                fn ($item) => $item->fresh()->received_base_qty >= $item->ordered_base_qty
            );

            $order->update([
                'status' => $allReceived ? PurchaseOrderStatus::Received : PurchaseOrderStatus::PartiallyReceived,
                'received_at' => $allReceived ? now() : null,
                'received_by' => auth()->id(),
            ]);

            // The canonical notification text ("Purchase order :reference
            // has been received.") describes a completed receipt: only
            // dispatch on `Received`, not on `PartiallyReceived` (there is
            // no separate partial-receipt event).
            if ($allReceived) {
                event(new \App\Events\PurchaseOrderReceived($order->id));
            }

            if ($order->update_cost_price) {
                // One current-price row per variant per receipt: several lines
                // may share a variant, so only the first line's cost wins.
                // (`$items` carries the post-update `received_base_qty`
                // values in memory — `update()` syncs attributes — while the
                // `$allReceived` completion check above re-reads each row via
                // `fresh()` so concurrent receipts are observed.)
                $priceUpdated = [];
                foreach ($items as $item) {
                    if ((int) ($receivedByItemId[$item->id] ?? 0) <= 0) {
                        continue;
                    }
                    if (isset($priceUpdated[$item->product_variant_id])) {
                        continue;
                    }
                    $priceUpdated[$item->product_variant_id] = true;
                    $this->updateCurrentCostPrice($item->product_variant_id, $item->unit_cost_price);
                }
            }
        });
    }

    /**
     * Cancel a Draft or Ordered purchase order.
     *
     * §6.4: legality is delegated to `PurchaseOrder::canBeCancelled()`
     * (§3.14), which requires Draft/Ordered status AND no item with
     * `received_base_qty > 0`.
     */
    public function cancelPurchaseOrder(PurchaseOrder $order): void
    {
        DB::transaction(function () use ($order) {
            $fresh = PurchaseOrder::lockForUpdate()->findOrFail($order->id);

            if (! $fresh->canBeCancelled()) {
                throw new InvalidDocumentStateException(
                    documentType: PurchaseOrder::class,
                    documentId: (int) $fresh->id,
                    actualStatus: $fresh->status->value,
                    action: 'cancel',
                );
            }

            $fresh->update([
                'status' => PurchaseOrderStatus::Cancelled,
                'cancelled_at' => now(),
            ]);

            // Dispatch purchase-order-cancelled event on cancellation
            event(new \App\Events\PurchaseOrderCancelled($order->id));
        });
    }

    /**
     * Replace the variant's current cost price, preserving the sale price.
     *
     * §6.4: no-op when the cost is unchanged. Otherwise: clear the
     * previous current row and insert a new one carrying the preserved
     * `sale_price`.
     */
    private function updateCurrentCostPrice(int $variantId, string $newCost): void
    {
        $variant = ProductVariant::lockForUpdate()->findOrFail($variantId);
        $current = $variant->currentPrice;

        if ($current && bccomp($current->cost_price, $newCost, 4) === 0) {
            return;
        }

        if ($current) {
            $current->update(['is_current' => false]);
        }

        ProductVariantPrice::create([
            'product_variant_id' => $variantId,
            'cost_price' => $newCost,
            'sale_price' => $current?->sale_price ?? '0.0000',
            'effective_from' => now(),
            'is_current' => true,
            'set_by' => auth()->id(),
        ]);
    }
}
