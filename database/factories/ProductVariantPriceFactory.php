<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariantPrice>
 */
class ProductVariantPriceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_variant_id' => ProductVariant::factory(),
            'cost_price' => fake()->randomFloat(4, 10, 500),
            'sale_price' => fake()->randomFloat(4, 20, 1000),
            'effective_from' => now(),
            'is_current' => true,
            'set_by' => null,
            'notes' => null,
        ];
    }

    public function notCurrent(): static
    {
        return $this->state(fn () => ['is_current' => false]);
    }

    public function forVariant(ProductVariant $variant): static
    {
        return $this->state(fn () => [
            'product_variant_id' => $variant->id,
        ]);
    }
}
