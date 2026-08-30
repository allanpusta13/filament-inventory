<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TransferOrderStatus;
use App\Models\TransferOrder;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransferOrder>
 */
final class TransferOrderFactory extends Factory
{
    protected $model = TransferOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference_number' => TransferOrder::generateReferenceNumber(),
            'sender_branch_id' => Warehouse::factory(),
            'receiver_branch_id' => Warehouse::factory(),
            'status' => TransferOrderStatus::Draft,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => TransferOrderStatus::Draft,
        ]);
    }

    public function requested(): static
    {
        return $this->state(fn (): array => [
            'status' => TransferOrderStatus::Requested,
        ]);
    }

    public function underReviewFulfiller(): static
    {
        return $this->state(fn (): array => [
            'status' => TransferOrderStatus::UnderReviewFulfiller,
        ]);
    }

    public function underReviewRequestor(): static
    {
        return $this->state(fn (): array => [
            'status' => TransferOrderStatus::UnderReviewRequestor,
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (): array => [
            'status' => TransferOrderStatus::Confirmed,
        ]);
    }

    public function dispatched(): static
    {
        return $this->state(fn (): array => [
            'status' => TransferOrderStatus::Dispatched,
            'dispatched_by' => User::factory(),
            'dispatched_at' => now(),
        ]);
    }

    public function received(): static
    {
        return $this->state(fn (): array => [
            'status' => TransferOrderStatus::Received,
            'dispatched_by' => User::factory(),
            'dispatched_at' => now()->subDay(),
            'received_by' => User::factory(),
            'received_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => TransferOrderStatus::Cancelled,
        ]);
    }
}
