<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderItem extends Model
{
    protected $fillable = [
        'sales_order_id', 'product_variant_id', 'unit_name', 'unit_ratio',
        'qty', 'base_qty', 'unit_sale_price_snapshot', 'dispatched_base_qty', 'notes',
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
        return max(0, $this->base_qty - $this->dispatched_base_qty);
    }

    public function lineTotal(): string
    {
        return bcmul((string) $this->dispatched_base_qty, (string) $this->unit_sale_price_snapshot, 4);
    }

    protected function casts(): array
    {
        return ['unit_sale_price_snapshot' => 'decimal:4'];
    }
}
