<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\GeneratesReferenceCodes;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * PurchaseOrder factory (§5.13).
 *
 * Default state: Draft, `PO-*` reference code. Named state:
 * `->ordered()`.
 */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        return [
            'reference_code' => GeneratesReferenceCodes::generateReferenceCode('PO', $this->faker->unique()->numberBetween(100, 999)),
            'supplier_id' => Supplier::factory(),
            'warehouse_id' => Warehouse::factory(),
            'status' => PurchaseOrderStatus::Draft,
            'ordered_by' => User::factory(),
        ];
    }

    public function ordered(): static
    {
        return $this->state(fn () => [
            'status' => PurchaseOrderStatus::Ordered,
            'ordered_at' => now(),
        ]);
    }
}
