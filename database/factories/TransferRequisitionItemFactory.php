<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * TransferRequisitionItem factory (§5.10).
 *
 * Default state: `pc` unit with ratio 1, requested qty in `[1, 20]`,
 * `requested_base_qty = requested_qty`. The approved leg is left null
 * — it is materialized at confirm time by
 * `NegotiationService::materializeRequestedAsApproved()` (§6.3).
 */
class TransferRequisitionItemFactory extends Factory
{
    protected $model = TransferRequisitionItem::class;

    public function definition(): array
    {
        $qty = $this->faker->numberBetween(1, 20);

        return [
            'transfer_requisition_id' => TransferRequisition::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'requested_unit_name' => 'pc',
            'requested_unit_ratio' => 1,
            'requested_qty' => $qty,
            'requested_base_qty' => $qty,
        ];
    }
}
