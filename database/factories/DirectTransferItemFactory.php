<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DirectTransfer;
use App\Models\DirectTransferItem;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DirectTransferItem>
 */
class DirectTransferItemFactory extends Factory
{
    protected $model = DirectTransferItem::class;

    public function definition(): array
    {
        return [
            'direct_transfer_id' => DirectTransfer::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'unit_name' => 'box',
            'unit_ratio' => fake()->numberBetween(1, 24),
            'qty' => fake()->numberBetween(1, 100),
            'base_qty' => fake()->numberBetween(1, 100),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
