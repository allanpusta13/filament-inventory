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
        return [
            'transfer_requisition_item_id' => TransferRequisitionItem::factory(),
            'user_id' => User::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'proposed_unit_name' => 'Box',
            'proposed_qty' => fake()->numberBetween(1, 10),
            'proposed_base_qty' => fake()->numberBetween(24, 240),
            'negotiation_reason' => fake()->sentence(),
            'side' => fake()->randomElement(NegotiationSide::cases()),
            'status' => RevisionStatus::Pending,
            'responds_to_revision_id' => null,
        ];
    }
}
