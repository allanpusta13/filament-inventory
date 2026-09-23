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
use Illuminate\Support\Facades\DB;

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
     * [FIX v11.1] Batch version of availableQuantity for N-variant queries.
     * Mirrors instance method logic exactly: on_hand - transfer_reserved - sales_reserved.
     * Returns [product_variant_id => availableQuantity] for all requested IDs.
     * Variant IDs with no movements/reservations correctly return 0.
     *
     * Issues exactly 3 queries regardless of variant count (1 for on_hand, 1 for transfer reservations, 1 for sales reservations).
     */
    public static function batchAvailableQuantity(array $variantIds, int $warehouseId): array
    {
        if (empty($variantIds)) {
            return [];
        }

        $onHand = StockMovement::whereIn('product_variant_id', $variantIds)
            ->where('warehouse_id', $warehouseId)
            ->selectRaw('product_variant_id, SUM(quantity) total')
            ->groupBy('product_variant_id')
            ->pluck('total', 'product_variant_id');

        $reservedTransfers = TransferRequisitionItem::whereIn('product_variant_id', $variantIds)
            ->whereHas('transferRequisition', function ($query) use ($warehouseId) {
                $query->where('from_warehouse_id', $warehouseId)
                    ->where('status', TransferRequisitionStatus::Confirmed);
            })
            ->selectRaw('product_variant_id, SUM(approved_base_qty) total')
            ->groupBy('product_variant_id')
            ->pluck('total', 'product_variant_id');

        $reservedSales = SalesOrderItem::whereIn('product_variant_id', $variantIds)
            ->whereHas('salesOrder', function ($query) use ($warehouseId) {
                $query->where('warehouse_id', $warehouseId)
                    ->where('status', SalesOrderStatus::Confirmed);
            })
            ->selectRaw('product_variant_id, SUM(base_qty - dispatched_base_qty) total')
            ->groupBy('product_variant_id')
            ->pluck('total', 'product_variant_id');

        return collect($variantIds)->mapWithKeys(function ($id) use ($onHand, $reservedTransfers, $reservedSales) {
            $available = (int) ($onHand[$id] ?? 0)
                - (int) ($reservedTransfers[$id] ?? 0)
                - (int) ($reservedSales[$id] ?? 0);

            return [$id => max(0, $available)];
        })->all();
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

    public function requisitionItems(): HasMany
    {
        return $this->hasMany(TransferRequisitionItem::class);
    }

    public function lossLedgers(): HasMany
    {
        return $this->hasMany(LossLedger::class);
    }

    public function isBelowReorderPoint(int $warehouseId): bool
    {
        return $this->availableQuantity($warehouseId) <= $this->reorder_point;
    }

    public function onHandQuantity(int $warehouseId): int
    {
        return (int) StockMovement::where('product_variant_id', $this->id)
            ->where('warehouse_id', $warehouseId)
            ->useIndex('stock_movements_product_variant_id_warehouse_id_index')
            ->sum('quantity');
    }

    /**
     * [FIX v11] Reservation scope is intentionally and permanently bounded
     * to Confirmed status only. Once TransferRequisition transitions to
     * Dispatched, the reserved quantity is superseded by the TransitOut
     * stock_movement (already reflected in onHandQuantity()). In-transit
     * and partially-received cargo is NOT considered "reserved" against
     * the origin warehouse — it has already left on_hand accounting
     * entirely at the moment of dispatch.
     *
     * Do NOT extend this query to include Dispatched or PartiallyReceived
     * statuses. Doing so would double-count stock that onHandQuantity()
     * has already deducted via the TransitOut movement, producing a
     * negative or understated availableQuantity() for any variant with
     * cargo currently in transit.
     */
    public function reservedQuantity(int $warehouseId): int
    {
        return (int) TransferRequisitionItem::where('product_variant_id', $this->id)
            ->whereHas('transferRequisition', function ($query) use ($warehouseId) {
                $query->where('from_warehouse_id', $warehouseId)
                    ->where('status', TransferRequisitionStatus::Confirmed);
            })
            ->sum('approved_base_qty');
    }

    /**
     * New in this addendum. Deliberately SEPARATE from reservedQuantity(),
     * which per [FIX v11] Principle #13 is permanently scoped to Confirmed
     * transfer_requisitions only and must not be widened. Sales reservations
     * are a distinct concern with a distinct lifecycle and are summed here
     * instead, then combined in availableQuantity() below.
     */
    public function reservedForSalesQuantity(int $warehouseId): int
    {
        return (int) SalesOrderItem::where('product_variant_id', $this->id)
            ->whereHas('salesOrder', function ($query) use ($warehouseId) {
                $query->where('warehouse_id', $warehouseId)
                    ->where('status', SalesOrderStatus::Confirmed);
            })
            ->sum(DB::raw('base_qty - dispatched_base_qty'));
    }

    /**
     * [FIX v11.1] availableQuantity() now nets out BOTH transfer reservations
     * and sales reservations. This REPLACES the parent blueprint's
     * availableQuantity() body — reservedQuantity() itself is untouched.
     */
    public function availableQuantity(int $warehouseId): int
    {
        return $this->onHandQuantity($warehouseId)
            - $this->reservedQuantity($warehouseId)
            - $this->reservedForSalesQuantity($warehouseId);
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
