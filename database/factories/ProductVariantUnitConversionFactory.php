<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\ProductVariantUnitConversion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariantUnitConversion>
 */
class ProductVariantUnitConversionFactory extends Factory
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
            'unit_name' => fake()->randomElement(['Box', 'Pallet', 'Case']),
            'base_unit_ratio' => fake()->numberBetween(6, 48),
            'is_default_purchase' => false,
            'is_default_transfer' => false,
        ];
    }

    public function piece(): static
    {
        return $this->state(fn () => [
            'unit_name' => 'Piece',
            'base_unit_ratio' => 1,
        ]);
    }

    public function box(): static
    {
        return $this->state(fn () => [
            'unit_name' => 'Box',
            'base_unit_ratio' => 24,
        ]);
    }

    public function case(): static
    {
        return $this->state(fn () => [
            'unit_name' => 'Case',
            'base_unit_ratio' => 12,
        ]);
    }

    public function pallet(): static
    {
        return $this->state(fn () => [
            'unit_name' => 'Pallet',
            'base_unit_ratio' => 48,
        ]);
    }

    public function forVariant(ProductVariant $variant): static
    {
        return $this->state(fn () => [
            'product_variant_id' => $variant->id,
        ]);
    }
}
