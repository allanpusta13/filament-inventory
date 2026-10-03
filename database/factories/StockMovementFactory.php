<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StockMovementType;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * StockMovement factory (§5.19).
 *
 * Default state: Adjustment, qty in `[1, 50]` (positive — the
 * `Adjustment` case is caller-signed, so a factory default is
 * positive), `pc` unit with ratio 1.
 *
 * NOTE: this factory writes a ledger row directly. In production,
 * `InventoryService::adjustment()` (§6.2) is the only legitimate
 * adjustment writer; the factory is for test fixtures only.
 */
class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    public function definition(): array
    {
        return [
            'product_variant_id' => ProductVariant::factory(),
            'warehouse_id' => Warehouse::factory(),
            'type' => StockMovementType::Adjustment,
            'quantity' => $this->faker->numberBetween(1, 50),
            'unit_name_used' => 'pc',
            'unit_ratio_used' => 1,
            'created_by' => User::factory(),
        ];
    }
}
