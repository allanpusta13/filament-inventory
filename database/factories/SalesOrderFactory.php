<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SalesOrderStatus;
use App\Models\Customer;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\GeneratesReferenceCodes;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * SalesOrder factory (§5.15).
 *
 * Default state: Draft, `SO-*` reference code. Named state:
 * `->confirmed()`.
 */
class SalesOrderFactory extends Factory
{
    protected $model = SalesOrder::class;

    public function definition(): array
    {
        return [
            'reference_code' => GeneratesReferenceCodes::generateReferenceCode('SO', $this->faker->unique()->numberBetween(100, 999)),
            'customer_id' => Customer::factory(),
            'warehouse_id' => Warehouse::factory(),
            'status' => SalesOrderStatus::Draft,
            'ordered_by' => User::factory(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => SalesOrderStatus::Confirmed,
            'confirmed_at' => now(),
        ]);
    }
}
