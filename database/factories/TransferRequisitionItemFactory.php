<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransferRequisitionItem>
 */
class TransferRequisitionItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $qty = fake()->numberBetween(1, 20);
        $ratio = 24;

        return [
            'transfer_requisition_id' => TransferRequisition::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'requested_unit_name' => 'Box',
            'requested_unit_ratio' => $ratio,
            'requested_qty' => $qty,
            'requested_base_qty' => $qty * $ratio,
            'shipped_base_qty' => 0,
            'received_good_base_qty' => 0,
            'received_damaged_base_qty' => 0,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'approved_unit_name' => $attributes['requested_unit_name'],
            'approved_unit_ratio' => $attributes['requested_unit_ratio'],
            'approved_qty' => $attributes['requested_qty'],
            'approved_base_qty' => $attributes['requested_base_qty'],
        ]);
    }

    public function dispatched(): static
    {
        return $this->state(fn (array $attributes) => [
            'approved_unit_name' => $attributes['requested_unit_name'],
            'approved_unit_ratio' => $attributes['requested_unit_ratio'],
            'approved_qty' => $attributes['requested_qty'],
            'approved_base_qty' => $attributes['requested_base_qty'],
            'shipped_base_qty' => $attributes['requested_base_qty'],
        ]);
    }

    public function received(): static
    {
        return $this->state(fn (array $attributes) => [
            'approved_unit_name' => $attributes['requested_unit_name'],
            'approved_unit_ratio' => $attributes['requested_unit_ratio'],
            'approved_qty' => $attributes['requested_qty'],
            'approved_base_qty' => $attributes['requested_base_qty'],
            'shipped_base_qty' => $attributes['requested_base_qty'],
            'received_good_base_qty' => $attributes['requested_base_qty'],
        ]);
    }
}
