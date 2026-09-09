<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => mb_strtoupper('WH-'.fake()->unique()->lexify('???')),
            'name' => fake()->city().' Warehouse',
            'location' => fake()->address(),
            'is_active' => true,
        ];
    }
}
