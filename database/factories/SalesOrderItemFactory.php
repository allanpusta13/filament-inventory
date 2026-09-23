<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesOrderItem>
 */
class SalesOrderItemFactory extends Factory
{
    protected $model = SalesOrderItem::class;

    public function definition(): array
    {
        return [
            'sales_order_id' => SalesOrder::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'unit_name' => fake()->randomElement(['Box', 'Carton', 'Pallet', 'Bag']),
            'unit_ratio' => fake()->numberBetween(1, 100),
            'qty' => fake()->numberBetween(1, 50),
            'unit_sale_price_snapshot' => '0.0000',
            'dispatched_base_qty' => 0,
            'notes' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (SalesOrderItem $item) {
            if ($item->base_qty === 0 || $item->base_qty === null) {
                $item->base_qty = $item->qty * $item->unit_ratio;
            }
        });
    }

    public function fullyDispatched(): static
    {
        return $this->state(fn (array $attributes) => [
            'dispatched_base_qty' => ($attributes['qty'] ?? 1) * ($attributes['unit_ratio'] ?? 1),
        ]);
    }

    public function partiallyDispatched(): static
    {
        return $this->state(fn (array $attributes) => [
            'dispatched_base_qty' => fake()->numberBetween(0, max(0, (($attributes['qty'] ?? 1) * ($attributes['unit_ratio'] ?? 1)) - 1)),
        ]);
    }
}
