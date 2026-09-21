<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id', 'product_variant_id', 'ordered_unit_name',
        'ordered_unit_ratio', 'ordered_qty', 'ordered_base_qty',
        'unit_cost_price', 'received_base_qty', 'notes',
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
        return max(0, $this->ordered_base_qty - $this->received_base_qty);
    }

    protected function casts(): array
    {
        return ['unit_cost_price' => 'decimal:4'];
    }
}
