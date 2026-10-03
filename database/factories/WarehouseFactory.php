<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Warehouse factory (§5.5).
 *
 * Produces a unique `WH-####` code, a city-based name, a full address
 * as location, and an active state.
 */
class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'code' => mb_strtoupper($this->faker->unique()->bothify('WH-####')),
            'name' => $this->faker->city().' Warehouse',
            'location' => $this->faker->address(),
            'is_active' => true,
        ];
    }
}
