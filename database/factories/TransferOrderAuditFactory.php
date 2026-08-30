<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TransferOrder;
use App\Models\TransferOrderAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransferOrderAudit>
 */
final class TransferOrderAuditFactory extends Factory
{
    protected $model = TransferOrderAudit::class;

    public function definition(): array
    {
        return [
            'transfer_order_id' => TransferOrder::factory(),
            'user_id' => User::factory(),
            'action' => fake()->randomElement(['submitted', 'reviewed', 'confirmed', 'dispatched', 'received', 'cancelled']),
            'changes_payload' => [
                'status' => [
                    'old' => 'draft',
                    'new' => 'requested',
                ],
            ],
        ];
    }
}
