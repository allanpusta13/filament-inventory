<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\StockMovementIdempotencyKey;
use App\Models\TransferRequisition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * StockMovementIdempotencyKey factory (§5.21).
 *
 * Produces a row with a unique sha256 checksum and an empty
 * `resulting_item_states` array. `$timestamps = false` on the model
 * (§3.22), so `created_at` is set explicitly.
 */
class StockMovementIdempotencyKeyFactory extends Factory
{
    protected $model = StockMovementIdempotencyKey::class;

    public function definition(): array
    {
        return [
            'transfer_requisition_id' => TransferRequisition::factory(),
            'payload_checksum' => hash('sha256', $this->faker->unique()->uuid()),
            'resulting_item_states' => [],
            'created_at' => now(),
        ];
    }
}
