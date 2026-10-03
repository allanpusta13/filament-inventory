<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * ProductVariantPrice factory (§5.3).
 *
 * Produces a current price row with a random cost and a 40% markup
 * sale price. `is_current = true` by default.
 */
class ProductVariantPriceFactory extends Factory
{
    protected $model = ProductVariantPrice::class;

    public function definition(): array
    {
        $cost = $this->faker->randomFloat(4, 1, 500);

        return [
            'product_variant_id' => ProductVariant::factory(),
            'cost_price' => $cost,
            'sale_price' => $cost * 1.4,
            'effective_from' => now(),
            'is_current' => true,
        ];
    }
}
