<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SalesOrderStatus;
use App\Models\Customer;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesOrder>
 */
class SalesOrderFactory extends Factory
{
    protected $model = SalesOrder::class;

    public function definition(): array
    {
        return [
            'reference_code' => 'SO-'.date('Ymd').'-'.mb_strtoupper(fake()->unique()->lexify('?????')),
            'customer_id' => Customer::factory(),
            'warehouse_id' => Warehouse::factory(),
            'status' => SalesOrderStatus::Draft,
            'ordered_by' => User::factory(),
            'ordered_at' => null,
            'confirmed_at' => null,
            'dispatched_at' => null,
            'cancelled_at' => null,
            'notes' => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => SalesOrderStatus::Confirmed,
            'ordered_at' => now(),
            'confirmed_at' => now(),
        ]);
    }

    public function partiallyDispatched(): static
    {
        return $this->state(fn () => [
            'status' => SalesOrderStatus::PartiallyDispatched,
            'ordered_at' => now(),
            'confirmed_at' => now(),
        ]);
    }

    public function dispatched(): static
    {
        return $this->state(fn () => [
            'status' => SalesOrderStatus::Dispatched,
            'ordered_at' => now(),
            'confirmed_at' => now(),
            'dispatched_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => SalesOrderStatus::Completed,
            'ordered_at' => now(),
            'confirmed_at' => now(),
            'dispatched_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => SalesOrderStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}
