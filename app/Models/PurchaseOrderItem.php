<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $purchase_order_id
 * @property int $product_variant_id
 * @property string $ordered_unit_name
 * @property int $ordered_unit_ratio
 * @property int $ordered_qty
 * @property int $ordered_base_qty
 * @property string $unit_cost_price
 * @property int $received_base_qty
 * @property string|null $notes
 * @property-read PurchaseOrder $purchaseOrder
 * @property-read ProductVariant $productVariant
 */
class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id', 'product_variant_id', 'ordered_unit_name',
        'ordered_unit_ratio', 'ordered_qty', 'ordered_base_qty',
        'unit_cost_price', 'received_base_qty', 'notes',
    ];

    protected $casts = [
        'ordered_unit_ratio' => 'integer',
        'ordered_qty' => 'integer',
        'ordered_base_qty' => 'integer',
        'unit_cost_price' => 'decimal:4',
        'received_base_qty' => 'integer',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function outstandingBaseQty(): int
    {
        return max(0, (int) $this->ordered_base_qty - (int) $this->received_base_qty);
    }
}
