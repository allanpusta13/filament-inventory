<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SalesOrderStatus;
use App\Enums\StockMovementType;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\StockMovement;
use App\Models\Warehouse;
use DomainException;
use Illuminate\Support\Facades\DB;

class SalesService
{
    public function __construct(
        private readonly GuardsOutstandingQuantity $guards,
    ) {}

    public function confirmSalesOrder(SalesOrder $order): void
    {
        DB::transaction(function () use ($order) {
            $fresh = SalesOrder::lockForUpdate()->findOrFail($order->id);

            if ($fresh->status !== SalesOrderStatus::Draft) {
                throw new DomainException('Only draft sales orders can be confirmed.');
            }

            $items = SalesOrderItem::where('sales_order_id', $fresh->id)
                ->lockForUpdate()
                ->get();

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
                throw new DomainException('Sales order is not in a dispatchable state.');
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

            foreach ($items as $item) {
                $dispatched = (int) ($dispatchByItemId[$item->id] ?? 0);
                if ($dispatched <= 0) {
                    continue;
                }

                $this->guards->assertSaleNotOverDispatched($item, $dispatched);

                $available = $availableByVariant[$item->product_variant_id] ?? 0;
                if ($dispatched > $available) {
                    throw new DomainException(
                        "Insufficient stock for variant {$item->product_variant_id}."
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
                $availableByVariant[$item->product_variant_id] = $available - $dispatched;
            }

            $allDispatched = $items->every(
                fn ($item) => $item->fresh()->dispatched_base_qty >= $item->base_qty
            );

            $order->update([
                'status' => $allDispatched ? SalesOrderStatus::Dispatched : SalesOrderStatus::PartiallyDispatched,
                'dispatched_at' => $allDispatched ? now() : null,
                'dispatched_by' => auth()->id(),
            ]);
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

            $alreadyReturned = (int) StockMovement::where('type', StockMovementType::SaleReturn->value)
                ->where('reference_type', SalesOrderItem::class)
                ->where('reference_id', (string) $item->id)
                ->sum('quantity');

            if ($alreadyReturned + $returnedBaseQty > $item->dispatched_base_qty) {
                throw new DomainException(
                    "Return of {$returnedBaseQty} would exceed dispatched quantity. Already returned: {$alreadyReturned}."
                );
            }

            StockMovement::create([
                'product_variant_id' => $item->product_variant_id,
                'warehouse_id' => $order->warehouse_id,
                'type' => StockMovementType::SaleReturn,
                'quantity' => abs($returnedBaseQty),
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

    public function cancelSalesOrder(SalesOrder $order): void
    {
        DB::transaction(function () use ($order) {
            $fresh = SalesOrder::lockForUpdate()->findOrFail($order->id);

            if (! in_array($fresh->status, [SalesOrderStatus::Draft, SalesOrderStatus::Confirmed], true)) {
                throw new DomainException('Sales order cannot be cancelled.');
            }

            $fresh->update([
                'status' => SalesOrderStatus::Cancelled,
                'cancelled_at' => now(),
            ]);
        });
    }
}
