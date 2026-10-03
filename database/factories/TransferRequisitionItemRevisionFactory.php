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
 * TransferRequisitionItemRevision factory (§5.20).
 *
 * Default state: Fulfiller-authored, Pending, `pc` unit with ratio 1,
 * proposed qty in `[1, 20]`. Optional `substitute_product_variant_id`
 * and `responds_to_revision_id` remain null by default.
 */
class TransferRequisitionItemRevisionFactory extends Factory
{
    protected $model = TransferRequisitionItemRevision::class;

    public function definition(): array
    {
        $qty = $this->faker->numberBetween(1, 20);

        return [
            'transfer_requisition_item_id' => TransferRequisitionItem::factory(),
            'user_id' => User::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'proposed_unit_name' => 'pc',
            'proposed_unit_ratio' => 1,
            'proposed_qty' => $qty,
            'proposed_base_qty' => $qty,
            'side' => NegotiationSide::Fulfiller,
            'status' => RevisionStatus::Pending,
        ];
    }
}
