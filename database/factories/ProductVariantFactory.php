<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => mb_strtoupper(fake()->unique()->bothify('PROD-???-###')),
            'barcode' => fake()->unique()->ean13(),
            'name' => fake()->words(2, true),
            'base_unit_name' => fake()->randomElement(['piece', 'gram', 'ml']),
            'cost_price' => fake()->randomFloat(4, 1, 100),
            'sale_price' => fake()->randomFloat(4, 5, 200),
            'reorder_point' => fake()->numberBetween(0, 100),
            'attributes' => ['roast' => fake()->randomElement(['Light', 'Medium', 'Dark'])],
            'images' => [],
        ];
    }
}
