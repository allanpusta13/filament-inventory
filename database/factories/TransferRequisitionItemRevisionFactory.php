<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\NegotiationSide;
use App\Enums\RevisionStatus;
use App\Models\ProductVariant;
use App\Models\TransferRequisitionItem;
use App\Models\TransferRequisitionItemRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransferRequisitionItemRevision>
 */
class TransferRequisitionItemRevisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unitRatio = fake()->numberBetween(1, 12);
        $qty = fake()->numberBetween(1, 10);

        return [
            'transfer_requisition_item_id' => TransferRequisitionItem::factory(),
            'user_id' => User::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'substitute_product_variant_id' => null,
            'proposed_unit_name' => 'Box',
            'proposed_unit_ratio' => $unitRatio,
            'proposed_qty' => $qty,
            'proposed_base_qty' => $qty * $unitRatio,
            'negotiation_reason' => fake()->sentence(),
            'side' => fake()->randomElement(NegotiationSide::cases()),
            'status' => RevisionStatus::Pending,
            'responds_to_revision_id' => null,
        ];
    }

    public function fromRequestor(): static
    {
        return $this->state(fn () => ['side' => NegotiationSide::Requestor]);
    }

    public function fromFulfiller(): static
    {
        return $this->state(fn () => ['side' => NegotiationSide::Fulfiller]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => ['status' => RevisionStatus::Accepted]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => RevisionStatus::Rejected]);
    }
}
