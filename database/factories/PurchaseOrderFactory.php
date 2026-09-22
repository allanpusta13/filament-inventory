<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        return [
            'reference_code' => 'PO-'.date('Ymd').'-'.mb_strtoupper(fake()->unique()->lexify('?????')),
            'supplier_id' => Supplier::factory(),
            'warehouse_id' => Warehouse::factory(),
            'status' => PurchaseOrderStatus::Draft,
            'update_cost_price' => false,
            'ordered_by' => User::factory(),
            'ordered_at' => null,
            'received_at' => null,
            'cancelled_at' => null,
            'notes' => null,
        ];
    }

    public function ordered(): static
    {
        return $this->state(fn () => [
            'status' => PurchaseOrderStatus::Ordered,
            'ordered_at' => now(),
        ]);
    }

    public function partiallyReceived(): static
    {
        return $this->state(fn () => [
            'status' => PurchaseOrderStatus::PartiallyReceived,
            'ordered_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => PurchaseOrderStatus::Completed,
            'ordered_at' => now(),
            'received_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => PurchaseOrderStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }

    public function withCostUpdate(): static
    {
        return $this->state(fn () => ['update_cost_price' => true]);
    }
}
