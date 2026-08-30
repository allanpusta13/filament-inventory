<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\TransferOrder;
use App\Models\TransferOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransferOrderItem>
 */
final class TransferOrderItemFactory extends Factory
{
    protected $model = TransferOrderItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transfer_order_id' => TransferOrder::factory(),
            'product_id' => Product::factory(),
            'requested_quantity' => fake()->numberBetween(1, 100),
            'approved_quantity' => null,
            'received_quantity' => null,
            'damaged_quantity' => 0,
            'item_status' => 'requested',
            'added_by_branch_id' => null,
            'variance_reason' => null,
        ];
    }

    public function requested(): static
    {
        return $this->state(fn (): array => [
            'item_status' => 'requested',
        ]);
    }

    public function approved(int $quantity): static
    {
        return $this->state(fn (): array => [
            'approved_quantity' => $quantity,
            'item_status' => 'approved',
        ]);
    }

    public function modified(int $approvedQuantity): static
    {
        return $this->state(fn (): array => [
            'approved_quantity' => $approvedQuantity,
            'item_status' => 'modified',
        ]);
    }

    public function added(int $quantity, int $addedByBranchId): static
    {
        return $this->state(fn (): array => [
            'requested_quantity' => $quantity,
            'approved_quantity' => $quantity,
            'item_status' => 'added',
            'added_by_branch_id' => $addedByBranchId,
        ]);
    }

    public function removed(): static
    {
        return $this->state(fn (): array => [
            'item_status' => 'removed',
        ]);
    }

    public function received(int $quantityReceived, ?string $varianceReason = null): static
    {
        return $this->state(fn (array $attributes) => array_merge($attributes, [
            'received_quantity' => $quantityReceived,
            'damaged_quantity' => 0,
            'variance_reason' => $varianceReason,
        ]));
    }

    public function partiallyReceived(int $quantityReceived, int $damagedQuantity, string $varianceReason): static
    {
        return $this->state(fn (array $attributes) => array_merge($attributes, [
            'received_quantity' => $quantityReceived,
            'damaged_quantity' => $damagedQuantity,
            'variance_reason' => $varianceReason,
        ]));
    }
}
