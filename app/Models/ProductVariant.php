<?php

declare(strict_types=1);

namespace App\Models;

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

    public function isBelowReorderPoint(int $currentBaseQty): bool
    {
        return $currentBaseQty <= $this->reorder_point;
    }

    public function onHandQuantity(int $warehouseId): int
    {
        return (int) StockMovement::where('product_variant_id', $this->id)
            ->where('warehouse_id', $warehouseId)
            ->sum('quantity');
    }

    /**
     * [FIX v10] Reservation scope is intentionally and permanently bounded
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

    public function availableQuantity(int $warehouseId): int
    {
        return $this->onHandQuantity($warehouseId) - $this->reservedQuantity($warehouseId);
    }

    protected function casts(): array
    {
        return [
            'attributes'    => 'array',
            'images'        => 'array',
            'reorder_point' => 'integer',
            'is_active'     => 'boolean',
        ];
    }
}