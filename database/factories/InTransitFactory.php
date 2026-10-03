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
 * InTransit factory (§5.11).
 *
 * Default state: InTransit, dispatched qty in `[1, 50]`, dispatched_at
 * set to now. Terminal `Cleared`/`Lost` states are exercised by
 * `InventoryService::markInTransit()` (§6.2) — the factory seeds the
 * initial state.
 */
class InTransitFactory extends Factory
{
    protected $model = InTransit::class;

    public function definition(): array
    {
        return [
            'transfer_requisition_id' => TransferRequisition::factory(),
            'transfer_requisition_item_id' => TransferRequisitionItem::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'dispatched_base_qty' => $this->faker->numberBetween(1, 50),
            'dispatched_at' => now(),
            'status' => InTransitStatus::InTransit,
        ];
    }
}
