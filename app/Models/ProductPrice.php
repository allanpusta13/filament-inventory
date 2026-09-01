<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ProductPrice extends Model
{
    protected $fillable = ['variant_id', 'warehouse_id', 'unit_name', 'price_type', 'price'];

    protected $casts = [
        'price' => 'decimal:4',
    ];

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
