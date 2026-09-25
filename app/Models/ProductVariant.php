<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SalesOrderStatus;
use App\Enums\TransferRequisitionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    /** @use HasFactory<\Database\Factories\ProductVariantFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'sku',
        'barcode',
        'name',
        'base_unit_name',
        'reorder_point',
        'attributes',
        'images',
        'is_active',
    ];

    /**
     * Batched available lookup — exactly 3 top-level aggregate queries
     * regardless of variant count (one per reservation class: on-hand,
     * transfer-reserved, sales-reserved). The `whereHas`/`CASE` subqueries
     * execute inside those 3 queries; they do not add top-level queries.
     *
     * Sales reservation mirrors `reservedForSalesQuantity()`: `Confirmed`
     * orders contribute full `base_qty`, `PartiallyDispatched` orders
     * contribute only `GREATEST(base_qty - dispatched_base_qty, 0)` via a
     * single joined aggregate (supported by both MySQL and PostgreSQL).
     *
     * @param  array<int>  $variantIds
     * @return array<int, int> variant_id => available_qty
     */
    public static function batchAvailableQuantity(
        array $variantIds,
        int $warehouseId,
        ?int $excludeSalesOrderId = null,
        ?int $excludeTransferRequisitionId = null,
    ): array {
        if (empty($variantIds)) {
            return [];
        }

        $onHand = StockMovement::query()
            ->selectRaw('product_variant_id, SUM(quantity) as total')
            ->whereIn('product_variant_id', $variantIds)
            ->where('warehouse_id', $warehouseId)
            ->groupBy('product_variant_id')
            ->pluck('total', 'product_variant_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $reserved = TransferRequisitionItem::query()
            ->selectRaw('product_variant_id, SUM(approved_base_qty) as total')
            ->whereIn('product_variant_id', $variantIds)
            ->whereNotNull('approved_base_qty')
            ->whereHas('transferRequisition', fn (Builder $q) => $q->where('status', TransferRequisitionStatus::Confirmed->value)
                ->where('from_warehouse_id', $warehouseId)
                ->when($excludeTransferRequisitionId, fn (Builder $qq) => $qq->where('id', '!=', $excludeTransferRequisitionId)))
            ->groupBy('product_variant_id')
            ->pluck('total', 'product_variant_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $salesReserved = SalesOrderItem::query()
            ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_items.sales_order_id')
            ->whereIn('sales_order_items.product_variant_id', $variantIds)
            ->whereIn('sales_orders.status', [
                SalesOrderStatus::Confirmed->value,
                SalesOrderStatus::PartiallyDispatched->value,
            ])
            ->where('sales_orders.warehouse_id', $warehouseId)
            ->when($excludeSalesOrderId, fn (Builder $qq) => $qq->where('sales_orders.id', '!=', $excludeSalesOrderId))
            ->selectRaw('sales_order_items.product_variant_id')
            ->selectRaw('SUM(CASE WHEN sales_orders.status = ? THEN sales_order_items.base_qty ELSE GREATEST(sales_order_items.base_qty - sales_order_items.dispatched_base_qty, 0) END) as total', [
                SalesOrderStatus::Confirmed->value,
            ])
            ->groupBy('sales_order_items.product_variant_id')
            ->pluck('total', 'product_variant_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $result = [];
        foreach ($variantIds as $id) {
            $result[$id] = ($onHand[$id] ?? 0)
                - ($reserved[$id] ?? 0)
                - ($salesReserved[$id] ?? 0);
        }

        return $result;
    }

    /**
     * Batched unit-conversion lookup — exactly 1 query.
     *
     * @param  array<int>  $variantIds
     * @return array<int, \Illuminate\Support\Collection>
     */
    public static function batchUnitConversions(array $variantIds): array
    {
        if (empty($variantIds)) {
            return [];
        }

        return ProductVariantUnitConversion::whereIn('product_variant_id', $variantIds)
            ->get()
            ->groupBy('product_variant_id')
            ->all();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unitConversions(): HasMany
    {
        return $this->hasMany(ProductVariantUnitConversion::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductVariantPrice::class);
    }

    public function currentPrice(): HasOne
    {
        return $this->hasOne(ProductVariantPrice::class)->where('is_current', true);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Physical on-hand = sum of all signed stock_movements quantities.
     */
    public function onHandQuantity(?int $warehouseId = null): int
    {
        return (int) $this->stockMovements()
            ->when($warehouseId, fn (Builder $q) => $q->where('warehouse_id', $warehouseId))
            ->sum('quantity');
    }

    /**
     * Reserved = sum of pending quantities from Confirmed requisitions only.
     * Scope boundary is intentional and permanent (Principle 13).
     *
     * $excludeTransferRequisitionId excludes the requisition currently being
     * dispatched so its own outstanding qty is not counted against itself.
     */
    public function reservedQuantity(
        ?int $warehouseId = null,
        ?int $excludeTransferRequisitionId = null,
    ): int {
        return (int) TransferRequisitionItem::query()
            ->where('product_variant_id', $this->id)
            ->whereNotNull('approved_base_qty')
            ->whereHas('transferRequisition', function (Builder $q) use ($warehouseId, $excludeTransferRequisitionId) {
                $q->where('status', TransferRequisitionStatus::Confirmed->value)
                    ->when($warehouseId, fn (Builder $qq) => $qq->where('from_warehouse_id', $warehouseId))
                    ->when($excludeTransferRequisitionId, fn (Builder $qq) => $qq->where('id', '!=', $excludeTransferRequisitionId));
            })
            ->sum('approved_base_qty');
    }

    /**
     * Sales reservation — separate from procurement reservation (A5).
     *
     * `Confirmed` orders reserve their full `base_qty`. `PartiallyDispatched`
     * orders reserve only the outstanding remainder
     * (`base_qty - dispatched_base_qty`, floored at 0 via
     * `SalesOrderItem::outstandingBaseQty()` semantics) — summing the full
     * `base_qty` would over-reserve stock already shipped.
     *
     * $excludeSalesOrderId excludes the sales order currently being dispatched
     * so its own outstanding qty is not counted against itself.
     */
    public function reservedForSalesQuantity(
        ?int $warehouseId = null,
        ?int $excludeSalesOrderId = null,
    ): int {
        $confirmed = (int) SalesOrderItem::query()
            ->where('product_variant_id', $this->id)
            ->whereHas('salesOrder', function (Builder $q) use ($warehouseId, $excludeSalesOrderId) {
                $q->where('status', SalesOrderStatus::Confirmed->value)
                    ->when($warehouseId, fn (Builder $qq) => $qq->where('warehouse_id', $warehouseId))
                    ->when($excludeSalesOrderId, fn (Builder $qq) => $qq->where('id', '!=', $excludeSalesOrderId));
            })
            ->sum('base_qty');

        $partiallyDispatched = (int) SalesOrderItem::query()
            ->where('product_variant_id', $this->id)
            ->whereHas('salesOrder', function (Builder $q) use ($warehouseId, $excludeSalesOrderId) {
                $q->where('status', SalesOrderStatus::PartiallyDispatched->value)
                    ->when($warehouseId, fn (Builder $qq) => $qq->where('warehouse_id', $warehouseId))
                    ->when($excludeSalesOrderId, fn (Builder $qq) => $qq->where('id', '!=', $excludeSalesOrderId));
            })
            ->selectRaw('SUM(GREATEST(base_qty - dispatched_base_qty, 0)) as total')
            ->value('total');

        return $confirmed + $partiallyDispatched;
    }

    /**
     * Available = on hand - reserved - reserved for sales.
     */
    public function availableQuantity(
        ?int $warehouseId = null,
        ?int $excludeSalesOrderId = null,
        ?int $excludeTransferRequisitionId = null,
    ): int {
        return $this->onHandQuantity($warehouseId)
            - $this->reservedQuantity($warehouseId, $excludeTransferRequisitionId)
            - $this->reservedForSalesQuantity($warehouseId, $excludeSalesOrderId);
    }

    /**
     * Blank barcodes normalize to null (owner decision, §2).
     */
    protected function barcode(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => filled($value) ? $value : null,
        );
    }

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'images' => 'array',
            'reorder_point' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
