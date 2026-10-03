<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\ProductVariantUnitConversion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * ProductVariantUnitConversion factory (§5.4).
 *
 * Default state: a non-base unit row (`box`/`case`/`pallet`) with a
 * ratio from `{6, 12, 24, 48}`. The `->baseUnit()` state produces the
 * `pc` self-conversion row for tests that bypass the observer.
 *
 * NOTE: `ProductVariantObserver` (§3.19) materializes the base-unit
 * row automatically on variant create. Seed the base row via this
 * factory only when the observer was intentionally bypassed.
 */
class ProductVariantUnitConversionFactory extends Factory
{
    protected $model = ProductVariantUnitConversion::class;

    public function definition(): array
    {
        return [
            'product_variant_id' => ProductVariant::factory(),
            'unit_name' => $this->faker->randomElement(['box', 'case', 'pallet']),
            'base_unit_ratio' => $this->faker->randomElement([6, 12, 24, 48]),
            'is_default_purchase' => false,
            'is_default_transfer' => false,
        ];
    }

    public function baseUnit(): static
    {
        return $this->state(fn () => [
            'unit_name' => 'pc',
            'base_unit_ratio' => 1,
        ]);
    }
}
