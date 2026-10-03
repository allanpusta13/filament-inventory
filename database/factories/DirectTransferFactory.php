<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DirectTransfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\GeneratesReferenceCodes;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * DirectTransfer factory (§5.17).
 *
 * Produces a header with a `DT-*` reference code, distinct from/to
 * warehouses, a factory-made transferrer, and `transferred_at = now`.
 */
class DirectTransferFactory extends Factory
{
    protected $model = DirectTransfer::class;

    public function definition(): array
    {
        return [
            'reference_code' => GeneratesReferenceCodes::generateReferenceCode('DT', $this->faker->unique()->numberBetween(100, 999)),
            'from_warehouse_id' => Warehouse::factory(),
            'to_warehouse_id' => Warehouse::factory(),
            'transferred_by' => User::factory(),
            'transferred_at' => now(),
            'notes' => $this->faker->sentence(),
        ];
    }
}
