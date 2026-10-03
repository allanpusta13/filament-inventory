<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DirectTransfer;
use App\Models\DirectTransferItem;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * DirectTransferItem factory (§5.18).
 *
 * Default state: `pc` unit with ratio 1, qty in `[1, 20]`,
 * `base_qty = qty`.
 */
class DirectTransferItemFactory extends Factory
{
    protected $model = DirectTransferItem::class;

    public function definition(): array
    {
        $qty = $this->faker->numberBetween(1, 20);

        return [
            'direct_transfer_id' => DirectTransfer::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'unit_name' => 'pc',
            'unit_ratio' => 1,
            'qty' => $qty,
            'base_qty' => $qty,
        ];
    }
}
