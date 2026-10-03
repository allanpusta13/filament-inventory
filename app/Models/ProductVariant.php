<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ProductVariant — the SKU-bearing, priced, stock-tracked unit.
 *
 * Blueprint §3.2. Every SKU, barcode, base unit, reorder point, price,
 * and stock balance belongs to a variant, never to its parent
 * `Product` family (§3.1). Stock levels are NEVER stored — they are
 * derived at query time from `stock_movements` (§0 core principle 1).
 *
 * Derived-stock methods (§3.2):
 *   - onHandQuantity()          — sum of signed stock_movements
 *   - reservedQuantity()        — Confirmed requisitions only (§0 p.13)
 *   - reservedForSalesQuantity() — Confirmed + PartiallyDispatched (A5)
 *   - availableQuantity()       — on hand − reserved − reserved for sales
 *   - batchAvailableQuantity()  — 3-query batched lookup (no N+1)
 *   - batchUnitConversions()    — 1-query batched lookup (no N+1)
 *
 * Observers (§3.19, registered §17.3): `ProductVariantObserver` creates
 * the base-unit self-conversion row on model creation (F19).
 *
 * Factory: `App\Database\Factories\ProductVariantFactory` (§5.2).
 * Policy:  `App\Policies\ProductVariantPolicy` (§8.2) — including the
 *          `adjustStock` ability used by QuickStockAdjustmentAction.
 */
class ProductVariant extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Mass-assignable attributes (§2.2).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id', 'sku', 'barcode', 'name', 'base_unit_name',
        'reorder_point', 'attributes', 'images', 'is_active',
    ];

    /**
     * Attribute casts (§2.2).
     *
     * @var array<string, string>
     */
    protected $casts = [
        'attributes' => 'array',
        'images' => 'array',
        'is_active' => 'boolean',
    ];

    // ---------------------------------------------------------------------
    // Derived stock — batched
    // ---------------------------------------------------------------------

    /**
     * Batched available lookup — exactly 3 top-level aggregate queries
     * regardless of variant count (one per reservation class: on-hand,
     * transfer-reserved, sales-reserved). The `whereHas`/`CASE` subqueries
     * execute inside those 3 queries; they do not add top-level queries.
     *
     * Sales reservation mirrors `reservedForSalesQuantity()`: `Confirmed`
     * orders contribute full `base_qty`, `PartiallyDispatched` orders
     * contribute only the outstanding remainder floored at 0 via a portable
     * `CASE WHEN ... ELSE 0 END` (MySQL, PostgreSQL, and SQLite) inside a
     * single joined aggregate.
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

        // Transfer reservation is booked against the effective (actual)
        // variant — the substitute when one was negotiated, otherwise the
        // requested variant — mirroring `dispatchTransfer()` availability
        // via `actualVariantId()`.
        $effectiveVariant = 'CASE WHEN substitute_product_variant_id IS NOT NULL THEN substitute_product_variant_id ELSE product_variant_id END';

        $reserved = TransferRequisitionItem::query()
            ->selectRaw("{$effectiveVariant} as effective_variant_id, SUM(approved_base_qty) as total")
            ->whereNotNull('approved_base_qty')
            ->where(fn (Builder $q) => $q
                ->whereIn('product_variant_id', $variantIds)
                ->orWhereIn('substitute_product_variant_id', $variantIds))
            ->whereHas('transferRequisition', fn (Builder $q) => $q->where('status', \App\Enums\TransferRequisitionStatus::Confirmed->value)
                ->where('from_warehouse_id', $warehouseId)
                ->when($excludeTransferRequisitionId, fn (Builder $qq) => $qq->where('id', '!=', $excludeTransferRequisitionId)))
            ->groupByRaw($effectiveVariant)
            ->pluck('total', 'effective_variant_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $salesReserved = SalesOrderItem::query()
            ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_items.sales_order_id')
            ->whereIn('sales_order_items.product_variant_id', $variantIds)
            ->whereIn('sales_orders.status', [
                \App\Enums\SalesOrderStatus::Confirmed->value,
                \App\Enums\SalesOrderStatus::PartiallyDispatched->value,
            ])
            ->where('sales_orders.warehouse_id', $warehouseId)
            ->when($excludeSalesOrderId, fn (Builder $qq) => $qq->where('sales_orders.id', '!=', $excludeSalesOrderId))
            ->selectRaw('sales_order_items.product_variant_id')
            ->selectRaw('SUM(CASE WHEN sales_orders.status = ? THEN sales_order_items.base_qty ELSE CASE WHEN sales_order_items.base_qty - sales_order_items.dispatched_base_qty > 0 THEN sales_order_items.base_qty - sales_order_items.dispatched_base_qty ELSE 0 END END) as total', [
                \App\Enums\SalesOrderStatus::Confirmed->value,
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

    // ---------------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------------

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

    // ---------------------------------------------------------------------
    // Derived stock — single variant
    // ---------------------------------------------------------------------

    /**
     * Physical on-hand = sum of all signed stock_movements quantities.
     *
     * The `(int)` cast is intentional defensive normalization (`sum()`
     * returns mixed depending on the driver); `quantity` is already an
     * integer column (§2.6), so the cast never changes the value.
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
     * Reservation is booked against the effective (actual) variant — the
     * substitute when one was negotiated, otherwise the requested variant —
     * mirroring `dispatchTransfer()` availability via `actualVariantId()`.
     *
     * `$excludeTransferRequisitionId` excludes the requisition currently
     * being dispatched so its own outstanding qty is not counted against
     * itself.
     */
    public function reservedQuantity(
        ?int $warehouseId = null,
        ?int $excludeTransferRequisitionId = null,
    ): int {
        return (int) TransferRequisitionItem::query()
            ->whereNotNull('approved_base_qty')
            ->where(fn (Builder $q) => $q->where(fn (Builder $qq) => $qq->where('product_variant_id', $this->id)
                ->whereNull('substitute_product_variant_id'))
                ->orWhere('substitute_product_variant_id', $this->id))
            ->whereHas('transferRequisition', function (Builder $q) use ($warehouseId, $excludeTransferRequisitionId) {
                $q->where('status', \App\Enums\TransferRequisitionStatus::Confirmed->value)
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
     * `$excludeSalesOrderId` excludes the sales order currently being dispatched
     * so its own outstanding qty is not counted against itself.
     */
    public function reservedForSalesQuantity(
        ?int $warehouseId = null,
        ?int $excludeSalesOrderId = null,
    ): int {
        $confirmed = (int) SalesOrderItem::query()
            ->where('product_variant_id', $this->id)
            ->whereHas('salesOrder', function (Builder $q) use ($warehouseId, $excludeSalesOrderId) {
                $q->where('status', \App\Enums\SalesOrderStatus::Confirmed->value)
                    ->when($warehouseId, fn (Builder $qq) => $qq->where('warehouse_id', $warehouseId))
                    ->when($excludeSalesOrderId, fn (Builder $qq) => $qq->where('id', '!=', $excludeSalesOrderId));
            })
            ->sum('base_qty');

        $partiallyDispatched = (int) SalesOrderItem::query()
            ->where('product_variant_id', $this->id)
            ->whereHas('salesOrder', function (Builder $q) use ($warehouseId, $excludeSalesOrderId) {
                $q->where('status', \App\Enums\SalesOrderStatus::PartiallyDispatched->value)
                    ->when($warehouseId, fn (Builder $qq) => $qq->where('warehouse_id', $warehouseId))
                    ->when($excludeSalesOrderId, fn (Builder $qq) => $qq->where('id', '!=', $excludeSalesOrderId));
            })
            ->selectRaw('SUM(CASE WHEN base_qty - dispatched_base_qty > 0 THEN base_qty - dispatched_base_qty ELSE 0 END) as total')
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
     * Blank barcodes normalize to null (owner decision, §2.2).
     *
     * `filled()` treats null, empty string, and whitespace-only strings
     * as blank, so this mutator is the backstop for the form-boundary
     * `dehydrateStateUsing()` normalization.
     */
    protected function barcode(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => filled($value) ? $value : null,
        );
    }
}
