<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\LossLedger;
use App\Models\ProductVariant;
use App\Models\TransferRequisition;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LossLedger>
 */
class LossLedgerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $lost = fake()->numberBetween(1, 20);
        $unitCost = fake()->randomFloat(4, 10, 200);

        return [
            'transfer_requisition_id' => TransferRequisition::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'warehouse_id' => Warehouse::factory(),
            'lost_base_qty' => $lost,
            'damaged_base_qty' => 0,
            'unit_cost_price' => $unitCost,
            'total_financial_loss' => round($lost * $unitCost, 4),
            'loss_category' => 'shortfall',
            'recorded_at' => now(),
        ];
    }
}
