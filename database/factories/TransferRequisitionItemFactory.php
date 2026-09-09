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
}
