<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\LossLedger;
use App\Models\ProductVariant;
use App\Models\TransferRequisition;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * LossLedger factory (§5.12).
 *
 * Produces a loss row with lost ∈ [0,10], damaged ∈ [0,5], a
 * random unit cost, and a total loss computed with `bcmul` at 4
 * decimal places — matching `LossLedger::calculateTotalFinancialLoss()`
 * (§3.11) so a factory-built row is never out of sync with the
 * service-written row shape.
 */
class LossLedgerFactory extends Factory
{
    protected $model = LossLedger::class;

    public function definition(): array
    {
        $lost = $this->faker->numberBetween(0, 10);
        $damaged = $this->faker->numberBetween(0, 5);
        $unitCost = $this->faker->randomFloat(4, 1, 100);

        return [
            'transfer_requisition_id' => TransferRequisition::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'warehouse_id' => Warehouse::factory(),
            'lost_base_qty' => $lost,
            'damaged_base_qty' => $damaged,
            'unit_cost_price' => $unitCost,
            'total_financial_loss' => bcmul((string) $unitCost, (string) ($lost + $damaged), 4),
            'loss_category' => $this->faker->randomElement(['shortfall', 'damage', 'spoilage', 'theft', 'other']),
            'recorded_at' => now(),
        ];
    }
}
