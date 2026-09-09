<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TransferRequisitionStatus;
use App\Models\TransferRequisition;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransferRequisition>
 */
class TransferRequisitionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
              'reference_code' => 'DTR-'.now()->format('Ymd').'-'.strtoupper(fake()->unique()->bothify('????')),
            'from_warehouse_id' => Warehouse::factory(),
            'to_warehouse_id' => Warehouse::factory(),
            'status' => TransferRequisitionStatus::Draft,
            'requested_by' => User::factory(),
        ];
    }
}
