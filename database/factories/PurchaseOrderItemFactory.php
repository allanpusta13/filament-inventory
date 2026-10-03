<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * PurchaseOrderItem factory (§5.14).
 *
 * Default state: `pc` unit with ratio 1, ordered qty in `[1, 20]`,
 * `ordered_base_qty = ordered_qty`, unit cost in `[1, 500]` at 4dp.
 * `received_base_qty` defaults to 0 (schema default).
 */
class PurchaseOrderItemFactory extends Factory
{
    protected $model = PurchaseOrderItem::class;

    public function definition(): array
    {
        $qty = $this->faker->numberBetween(1, 20);

        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'ordered_unit_name' => 'pc',
            'ordered_unit_ratio' => 1,
            'ordered_qty' => $qty,
            'ordered_base_qty' => $qty,
            'unit_cost_price' => $this->faker->randomFloat(4, 1, 500),
        ];
    }
}
