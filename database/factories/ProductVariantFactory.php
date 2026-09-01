<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductVariant>
 */
final class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition()
    {
        return [
            'product_id' => \App\Models\Product::factory(),
            'sku' => $this->faker->unique()->bothify('??-#####'),
            'barcode' => $this->faker->ean13(),
            'name' => $this->faker->words(2, true),
            'attributes' => json_encode([]),
            'images' => json_encode([]),
            'cost_price' => $this->faker->randomFloat(4, 0, 1000),
            'sale_price' => $this->faker->randomFloat(4, 0, 2000),
            'base_unit_name' => $this->faker->randomElement(['pcs', 'kg', 'l', 'm', 'box']),
        ];
    }
}
