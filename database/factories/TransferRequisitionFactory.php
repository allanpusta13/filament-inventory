<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TransferRequisition;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

final class TransferRequisitionFactory extends Factory
{
    protected $model = TransferRequisition::class;

    public function definition(): array
    {
        return [
            'reference_code' => 'TRQ-'.mb_strtoupper(fake()->bothify('???-####')),
            'from_warehouse_id' => Warehouse::factory(),
            'to_warehouse_id' => Warehouse::factory(),
            'status' => 'draft',
            'requested_by' => User::factory(),
            'requested_at' => fake()->dateTime(),
        ];
    }
}
