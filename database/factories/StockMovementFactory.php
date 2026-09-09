<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MovementType;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
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
            'warehouse_id' => Warehouse::factory(),
            'type' => MovementType::Receive,
            'quantity' => fake()->numberBetween(1, 500),
            'unit_name_used' => 'piece',
            'unit_ratio_used' => 1,
            'reference_code' => 'DTR-'.now()->format('Ymd').'-'.strtoupper(fake()->bothify('????')),
        ];
    }
}
