<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TransferRequisitionStatus;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
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
            'reference_code' => 'DTR-'.now()->format('Ymd').'-'.mb_strtoupper(fake()->unique()->bothify('????')),
            'from_warehouse_id' => Warehouse::factory(),
            'to_warehouse_id' => Warehouse::factory(),
            'status' => TransferRequisitionStatus::Draft,
            'requested_by' => User::factory(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => TransferRequisitionStatus::Requested]);
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => TransferRequisitionStatus::Confirmed]);
    }

    public function dispatched(): static
    {
        return $this->state(fn () => ['status' => TransferRequisitionStatus::Dispatched]);
    }

    public function received(): static
    {
        return $this->state(fn () => ['status' => TransferRequisitionStatus::Completed]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => TransferRequisitionStatus::Cancelled]);
    }

    public function closedWithLoss(): static
    {
        return $this->state(fn () => ['status' => TransferRequisitionStatus::ClosedWithLoss]);
    }

    public function underReviewFulfiller(): static
    {
        return $this->state(fn () => ['status' => TransferRequisitionStatus::UnderReviewFulfiller]);
    }

    public function underReviewRequestor(): static
    {
        return $this->state(fn () => ['status' => TransferRequisitionStatus::UnderReviewRequestor]);
    }

    public function withItems(): static
    {
        return $this->afterCreating(function (TransferRequisition $requisition) {
            TransferRequisitionItem::factory()
                ->count(fake()->numberBetween(1, 3))
                ->for($requisition)
                ->create();
        });
    }
}
