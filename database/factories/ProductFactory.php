<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Product factory (§5.1).
 *
 * Produces a family container with a random word-based name and a
 * category from the small fixed set. Variants are created separately
 * via `ProductVariantFactory` (§5.2) and attach through `product_id`.
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'category' => $this->faker->randomElement(['Electronics', 'Hardware', 'Consumables']),
        ];
    }
}
