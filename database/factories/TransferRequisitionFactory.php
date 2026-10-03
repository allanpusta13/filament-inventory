<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TransferRequisitionStatus;
use App\Models\TransferRequisition;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\GeneratesReferenceCodes;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * TransferRequisition factory (§5.9).
 *
 * Default state: Draft, `TR-*` reference code, distinct from/to
 * warehouses, a factory-made requester. Named states:
 * `->requested()`, `->confirmed()`, `->dispatched()`.
 *
 * The random part of the reference code is deterministic
 * (`unique()->numberBetween(100, 999)`) so a seed collision fails at
 * the DB layer instead of silently duplicating (§2 reference-code
 * contract).
 */
class TransferRequisitionFactory extends Factory
{
    protected $model = TransferRequisition::class;

    public function definition(): array
    {
        return [
            'reference_code' => GeneratesReferenceCodes::generateReferenceCode('TR', $this->faker->unique()->numberBetween(100, 999)),
            'from_warehouse_id' => Warehouse::factory(),
            'to_warehouse_id' => Warehouse::factory(),
            'status' => TransferRequisitionStatus::Draft,
            'requested_by' => User::factory(),
        ];
    }

    public function requested(): static
    {
        return $this->state(fn () => [
            'status' => TransferRequisitionStatus::Requested,
            'requested_at' => now(),
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => TransferRequisitionStatus::Confirmed,
            'approved_at' => now(),
            'approved_by' => User::factory(),
        ]);
    }

    public function dispatched(): static
    {
        return $this->state(fn () => [
            'status' => TransferRequisitionStatus::Dispatched,
            'dispatched_at' => now(),
            'dispatched_by' => User::factory(),
        ]);
    }
}
