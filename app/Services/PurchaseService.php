<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMovementType;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Models\Warehouse;
use DomainException;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    public function __construct(
        private readonly GuardsOutstandingQuantity $guards,
    ) {}

    public function orderPurchase(PurchaseOrder $order): void
    {
        DB::transaction(function () use ($order) {
            $fresh = PurchaseOrder::lockForUpdate()->findOrFail($order->id);

            if ($fresh->status !== PurchaseOrderStatus::Draft) {
                throw new DomainException('Only draft purchase orders can be ordered.');
            }

            $fresh->update([
                'status' => PurchaseOrderStatus::Ordered,
                'ordered_at' => now(),
                'ordered_by' => $fresh->ordered_by ?? auth()->id(),
            ]);
        });
    }

    /**
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
                throw new DomainException('Purchase order is not in a receivable state.');
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

                if ($order->update_cost_price) {
                    $this->updateCurrentCostPrice($item->product_variant_id, $item->unit_cost_price);
                }
            }

            $allReceived = $items->every(
                fn ($item) => $item->fresh()->received_base_qty >= $item->ordered_base_qty
            );

            $order->update([
                'status' => $allReceived ? PurchaseOrderStatus::Received : PurchaseOrderStatus::PartiallyReceived,
                'received_at' => $allReceived ? now() : null,
                'received_by' => auth()->id(),
            ]);
        });
    }

    public function cancelPurchaseOrder(PurchaseOrder $order): void
    {
        DB::transaction(function () use ($order) {
            $fresh = PurchaseOrder::lockForUpdate()->findOrFail($order->id);

            if (! $fresh->canBeCancelled()) {
                throw new DomainException('Purchase order cannot be cancelled.');
            }

            $fresh->update([
                'status' => PurchaseOrderStatus::Cancelled,
                'cancelled_at' => now(),
            ]);
        });
    }

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
