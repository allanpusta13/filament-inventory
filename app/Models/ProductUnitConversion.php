<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ProductUnitConversion extends Model
{
    protected $fillable = ['variant_id', 'unit_name', 'base_unit_ratio', 'is_default_purchase', 'is_default_transfer'];

    protected $casts = [
        'is_default_purchase' => 'boolean',
        'is_default_transfer' => 'boolean',
    ];

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
