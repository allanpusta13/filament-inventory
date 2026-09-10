<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockMovementType;
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

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function transferRequisitionItems(): HasMany
    {
        return $this->hasMany(TransferRequisitionItem::class);
    }

    /**
     * The single active price row for this variant.
     * App logic is responsible for keeping exactly one is_current=true row per variant;
     * the DB enforces this with a partial/generated unique index (see migration).
     */
    public function currentPrice(): HasOne
    {
        return $this->hasOne(ProductVariantPrice::class)->where('is_current', true);
    }

    public function isBelowReorderPoint(int $currentBaseQty): bool
    {
        return $currentBaseQty <= $this->reorder_point;
    }

    /**-------------------------------------------------------
     * Derived Stock of Truth — calculated dynamically from
     * stock_movements and transfer_requisition_items.
     * Never store physical stock in a denormalized column.
     *-------------------------------------------------------*/

    public function onHandQuantity(?int $warehouseId = null): int
    {
        $query = $this->stockMovements()
            ->whereNotNull('quantity');

        if ($warehouseId !== null) {
            $query->where('warehouse_id', $warehouseId);
        }

        return $query->sum('quantity');
    }

    public function reservedQuantity(): int
    {
        $items = $this->transferRequisitionItems()
            ->whereStatus('pending')
            ->get();

        $total = 0;
        foreach ($items as $item) {
            $total += max(0, $item->approved_base_qty - $item->received_good_base_qty);
        }

        return $total;
    }

    public function availableQuantity(): int
    {
        return $this->onHandQuantity() - $this->reservedQuantity();
    }

    public function incomingStock(): int
    {
        $movements = $this->stockMovements()
            ->where('type', StockMovementType::TransferIn)
            ->get();

        $total = 0;
        foreach ($movements as $q) {
            $total += $q->quantity;
        }

        return $total;
    }

    public function outgoingStock(): int
    {
        $movements = $this->stockMovements()
            ->where('type', StockMovementType::TransferOut)
            ->get();

        $total = 0;
        foreach ($movements as $q) {
            $total += $q->quantity;
        }

        return $total;
    }

    public function lossStock(): int
    {
        $movements = $this->stockMovements()
            ->where('type', StockMovementType::Loss)
            ->get();

        $total = 0;
        foreach ($movements as $q) {
            $total += $q->quantity;
        }

        return $total;
    }

    public function stockLevel(): string
    {
        $onHand = $this->onHandQuantity();
        $reserved = $this->reservedQuantity();

        if ($onHand <= 0) {
            return 'critical';
        }
        if ($onHand <= $reserved) {
            return 'low';
        }
        if ($onHand <= $this->reorder_point + $reserved) {
            return 'medium';
        }

        return 'full';
    }

    public function urgencyLevel(): string
    {
        $onHand = $this->onHandQuantity();
        $reserved = $this->reservedQuantity();

        if ($onHand <= 0) {
            return 'critical';
        }
        if ($onHand <= $reserved) {
            return 'high';
        }
        if ($onHand <= $this->reorder_point + $reserved) {
            return 'medium';
        }

        return 'none';
    }

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'images' => 'array',
            'reorder_point' => 'integer',
        ];
    }
}
