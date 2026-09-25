<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\ProductVariant;
use App\Models\ProductVariantUnitConversion;

class ProductVariantObserver
{
    public function created(ProductVariant $variant): void
    {
        ProductVariantUnitConversion::firstOrCreate(
            [
                'product_variant_id' => $variant->id,
                'unit_name' => $variant->base_unit_name,
            ],
            [
                'base_unit_ratio' => 1,
                'is_default_purchase' => false,
                'is_default_transfer' => false,
            ],
        );
    }
}
