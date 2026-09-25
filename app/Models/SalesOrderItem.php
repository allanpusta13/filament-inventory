<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $sales_order_id
 * @property int $product_variant_id
 * @property string $unit_name
 * @property int $unit_ratio
 * @property int $qty
 * @property int $base_qty
 * @property string $unit_sale_price_snapshot
 * @property int $dispatched_base_qty
 * @property string|null $notes
 * @property-read SalesOrder $salesOrder
 * @property-read ProductVariant $productVariant
 */
class SalesOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_order_id', 'product_variant_id', 'unit_name', 'unit_ratio',
        'qty', 'base_qty', 'unit_sale_price_snapshot', 'dispatched_base_qty', 'notes',
    ];

    protected $casts = [
        'unit_ratio' => 'integer',
        'qty' => 'integer',
        'base_qty' => 'integer',
        'unit_sale_price_snapshot' => 'decimal:4',
        'dispatched_base_qty' => 'integer',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function outstandingBaseQty(): int
    {
        return max(0, (int) $this->base_qty - (int) $this->dispatched_base_qty);
    }

    public function alreadyReturnedBaseQty(): int
    {
        return (int) StockMovement::query()
            ->where('type', StockMovementType::SaleReturn->value)
            ->where('reference_type', self::class)
            ->where('reference_id', (string) $this->id)
            ->sum('quantity');
    }
}
