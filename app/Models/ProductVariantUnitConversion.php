<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariantUnitConversion extends Model
{
    /** @use HasFactory<\Database\Factories\ProductVariantUnitConversionFactory> */
    use HasFactory;

    protected $fillable = [
        'product_variant_id',
        'unit_name',
        'base_unit_ratio',
        'is_default_purchase',
        'is_default_transfer',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function toBaseUnits(int $qtyInThisUnit): int
    {
        return $qtyInThisUnit * $this->base_unit_ratio;
    }

    protected function casts(): array
    {
        return [
            'base_unit_ratio' => 'integer',
            'is_default_purchase' => 'boolean',
            'is_default_transfer' => 'boolean',
        ];
    }
}
