<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderItem>
 */
class PurchaseOrderItemFactory extends Factory
{
    protected $model = PurchaseOrderItem::class;

    public function definition(): array
    {
        $qty = fake()->numberBetween(1, 50);
        $unitRatio = fake()->numberBetween(1, 100);

        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'ordered_unit_name' => fake()->randomElement(['Box', 'Carton', 'Pallet', 'Bag']),
            'ordered_unit_ratio' => $unitRatio,
            'ordered_qty' => $qty,
            'ordered_base_qty' => $qty * $unitRatio,
            'unit_cost_price' => fake()->randomFloat(4, 1, 1000),
            'received_base_qty' => 0,
            'notes' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (PurchaseOrderItem $item) {
            // Compute ordered_base_qty only if it wasn't explicitly set (i.e., is zero)
            // This allows state() to override ordered_base_qty directly
            if ($item->ordered_base_qty === 0) {
                $item->ordered_base_qty = $item->ordered_qty * $item->ordered_unit_ratio;
            }
        });
    }

    public function fullyReceived(): static
    {
        return $this->state(fn (array $attributes) => [
            'received_base_qty' => ($attributes['ordered_qty'] ?? 1) * ($attributes['ordered_unit_ratio'] ?? 1),
        ]);
    }

    public function partiallyReceived(): static
    {
        return $this->state(fn (array $attributes) => [
            'received_base_qty' => fake()->numberBetween(0, max(0, (($attributes['ordered_qty'] ?? 1) * ($attributes['ordered_unit_ratio'] ?? 1)) - 1)),
        ]);
    }
}
