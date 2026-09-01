<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MovementType;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
final class StockMovementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'variant_id' => \App\Models\ProductVariant::factory(),
            'warehouse_id' => Warehouse::factory(),
            'type' => MovementType::Receive,
            'quantity' => fake()->numberBetween(1, 100),
            'unit_name_used' => 'Base Unit',
            'unit_ratio_used' => 1,
            'reference_code' => fake()->optional()->words(3, true),
            'created_by' => User::factory(),
        ];
    }
}
