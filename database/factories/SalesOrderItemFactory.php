<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * SalesOrderItem factory (§5.16).
 *
 * Default state: `pc` unit with ratio 1, qty in `[1, 20]`,
 * `base_qty = qty`, snapshot price in `[1, 500]` at 4dp.
 * `dispatched_base_qty` defaults to 0 (schema default).
 *
 * NOTE: the snapshot price here is a factory convenience. In production,
 * `unit_sale_price_snapshot` starts at `0.0000` and is only written by
 * `SalesService::confirmSalesOrder()` (§6.5) from the locked variant's
 * current price.
 */
class SalesOrderItemFactory extends Factory
{
    protected $model = SalesOrderItem::class;

    public function definition(): array
    {
        $qty = $this->faker->numberBetween(1, 20);

        return [
            'sales_order_id' => SalesOrder::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'unit_name' => 'pc',
            'unit_ratio' => 1,
            'qty' => $qty,
            'base_qty' => $qty,
            'unit_sale_price_snapshot' => $this->faker->randomFloat(4, 1, 500),
        ];
    }
}
