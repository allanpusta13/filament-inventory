<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * ProductVariant factory (§5.2).
 *
 * Produces a variant with a unique `SKU-####-??` sku, a unique
 * EAN-13 barcode, a `pc` base unit, and an active state. The base-unit
 * self-conversion row is materialized by `ProductVariantObserver`
 * (§3.19) on create — the factory does NOT seed it.
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => mb_strtoupper($this->faker->unique()->bothify('SKU-####-??')),
            'barcode' => $this->faker->unique()->ean13(),
            'name' => $this->faker->words(2, true),
            'base_unit_name' => 'pc',
            'reorder_point' => $this->faker->numberBetween(0, 50),
            'is_active' => true,
        ];
    }
}
