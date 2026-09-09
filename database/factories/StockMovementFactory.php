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
            'reference_code' => 'DTR-'.now()->format('Ymd').'-'.mb_strtoupper(fake()->bothify('????')),
        ];
    }

    public function receive(): static
    {
        return $this->state(fn () => ['type' => MovementType::Receive]);
    }

    public function ship(): static
    {
        return $this->state(fn () => ['type' => MovementType::Ship]);
    }

    public function transferIn(): static
    {
        return $this->state(fn () => ['type' => MovementType::TransferIn]);
    }

    public function transferOut(): static
    {
        return $this->state(fn () => ['type' => MovementType::TransferOut]);
    }

    public function adjustment(): static
    {
        return $this->state(fn () => ['type' => MovementType::Adjustment]);
    }

    public function loss(): static
    {
        return $this->state(fn () => ['type' => MovementType::Loss]);
    }
}
