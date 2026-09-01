<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\LossLedger;
use App\Models\ProductVariant;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

final class LossLedgerFactory extends Factory
{
    protected $model = LossLedger::class;

    public function definition(): array
    {
        $variant = ProductVariant::factory()->create();
        $requisition = TransferRequisition::factory()->create();
        $requisitionItem = TransferRequisitionItem::factory()->create([
            'requisition_id' => $requisition->id,
            'variant_id' => $variant->id,
        ]);

        return [
            'requisition_id' => $requisition->id,
            'requisition_item_id' => $requisitionItem->id,
            'variant_id' => $variant->id,
            'warehouse_id' => Warehouse::factory(),
            'lost_base_qty' => fake()->numberBetween(0, 100),
            'damaged_base_qty' => fake()->numberBetween(0, 100),
            'unit_cost_price' => fake()->randomFloat(4, 1, 100),
            'total_financial_loss' => fake()->randomFloat(4, 1, 1000),
            'loss_category' => fake()->randomElement(['Transit Variance', 'Damage', 'Theft', 'Other']),
            'recorded_by' => User::factory(),
            'recorded_at' => fake()->dateTime(),
        ];
    }
}
