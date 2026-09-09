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
            'sku' => strtoupper(fake()->unique()->bothify('PROD-???-###')),
            'barcode' => fake()->unique()->ean13(),
            'name' => fake()->words(2, true),
            'base_unit_name' => fake()->randomElement(['piece', 'gram', 'ml']),
            'reorder_point' => fake()->numberBetween(0, 100),
            'attributes' => ['roast' => fake()->randomElement(['Light', 'Medium', 'Dark'])],
            'images' => [],
        ];
    }
}
