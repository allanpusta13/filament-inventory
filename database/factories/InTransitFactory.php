<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InTransitStatus;
use App\Models\InTransit;
use App\Models\ProductVariant;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InTransit>
 */
class InTransitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transfer_requisition_id' => TransferRequisition::factory(),
            'transfer_requisition_item_id' => TransferRequisitionItem::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'dispatched_base_qty' => fake()->numberBetween(10, 500),
            'dispatched_at' => now(),
            'status' => InTransitStatus::InTransit,
        ];
    }

    public function inTransit(): static
    {
        return $this->state(fn () => ['status' => InTransitStatus::InTransit]);
    }

    public function received(): static
    {
        return $this->state(fn () => ['status' => InTransitStatus::Cleared]);
    }

    public function partiallyReceived(): static
    {
        return $this->state(fn () => ['status' => InTransitStatus::PartiallyReceived]);
    }

    public function cleared(): static
    {
        return $this->state(fn () => ['status' => InTransitStatus::Cleared]);
    }
}
