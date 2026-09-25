<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DirectTransfer;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DirectTransfer>
 */
class DirectTransferFactory extends Factory
{
    protected $model = DirectTransfer::class;

    public function definition(): array
    {
        return [
            'reference_code' => 'DT-'.now()->format('Ymd').'-'.mb_strtoupper(fake()->unique()->bothify('????')),
            'from_warehouse_id' => Warehouse::factory(),
            'to_warehouse_id' => Warehouse::factory(),
            'transferred_by' => User::factory(),
            'notes' => fake()->optional()->sentence(),
            'transferred_at' => now(),
        ];
    }
}
