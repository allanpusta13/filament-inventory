<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\LossLedger;
use App\Models\ProductVariant;
use App\Models\TransferRequisition;
use App\Models\User;
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
        $unitCost = '0.0000';

        return [
            'transfer_requisition_id' => TransferRequisition::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'warehouse_id' => Warehouse::factory(),
            'lost_base_qty' => $lost,
            'damaged_base_qty' => 0,
            'unit_cost_price' => $unitCost,
            'total_financial_loss' => bcmul((string) $lost, $unitCost, 4),
            'loss_category' => 'shortfall',
            'recorded_by' => User::factory(),
            'recorded_at' => now(),
        ];
    }

    public function forVariant(ProductVariant $variant): static
    {
        return $this->state(fn () => [
            'product_variant_id' => $variant->id,
            // [FIX v10] Snapshot cost from variant's current price at creation time
            // to match LossLedger::snapshotUnitCostFrom() behavior
        ])->afterCreating(function (LossLedger $loss) use ($variant) {
            if ($loss->unit_cost_price === '0.0000') {
                $loss->update([
                    'unit_cost_price' => LossLedger::snapshotUnitCostFrom($variant),
                    'total_financial_loss' => bcmul(
                        (string) ($loss->lost_base_qty + $loss->damaged_base_qty),
                        LossLedger::snapshotUnitCostFrom($variant),
                        4
                    ),
                ]);
            }
        });
    }
}
