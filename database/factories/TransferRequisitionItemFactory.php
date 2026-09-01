<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

final class TransferRequisitionItemFactory extends Factory
{
    protected $model = TransferRequisitionItem::class;

    public function definition(): array
    {
        $qty = fake()->numberBetween(10, 1000);

        return [
            'requisition_id' => TransferRequisition::factory(),
            'variant_id' => ProductVariant::factory(),
            'requested_unit_name' => 'piece',
            'requested_unit_ratio' => 1,
            'requested_qty' => $qty,
            'requested_base_qty' => $qty,
        ];
    }
}
