<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NegotiationSide;
use App\Enums\RevisionStatus;
use App\Enums\TransferRequisitionStatus;
use App\Exceptions\NegotiationNotAllowedException;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use App\Models\TransferRequisitionItemRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates the negotiation lifecycle for a transfer requisition item:
 * proposing, accepting, rejecting, and countering revisions. Accepting a
 * revision is the ONLY path that populates a TransferRequisitionItem's
 * approved_unit_name / approved_unit_ratio / approved_qty / approved_base_qty
 * / substitute_product_variant_id fields — InventoryService::dispatchTransfer()
 * reads those fields assuming they reflect an accepted revision (or, if no
 * negotiation ever occurred, the original requested_* values as a fallback).
 */
class NegotiationService
{
    /**
     * Guard: verify the requisition is in a negotiable status and the revision
     * is still pending. Throws NegotiationNotAllowedException with actionable
     * message if guard fails.
     */
    public function assertNegotiable(TransferRequisitionItemRevision $revision, string $action): void
    {
        $requisition = $revision->item->transferRequisition;

        if (! in_array($requisition->status, [
            TransferRequisitionStatus::Requested,
            TransferRequisitionStatus::UnderReviewFulfiller,
            TransferRequisitionStatus::UnderReviewRequestor,
        ], true)) {
            throw new NegotiationNotAllowedException($requisition, $action);
        }

        if ($revision->status->isResolved()) {
            throw new NegotiationNotAllowedException(
                $requisition,
                "{$action} (revision {$revision->id} is {$revision->status->value})"
            );
        }

        // Optional: verify side matches current turn
        $expectedSide = match ($requisition->status) {
            TransferRequisitionStatus::UnderReviewFulfiller => NegotiationSide::Fulfiller,
            TransferRequisitionStatus::UnderReviewRequestor => NegotiationSide::Requestor,
            default => null,
        };

        if ($expectedSide !== null && $revision->side !== $expectedSide) {
            throw new NegotiationNotAllowedException(
                $requisition,
                "{$action} (wrong side: {$revision->side->value}, expected {$expectedSide->value})"
            );
        }
    }

    /**
     * Open a new negotiation thread on an item, or start a counter-thread if
     * $respondsTo is given. Prefer TransferRequisitionItemRevision::counterWith()
     * directly when you already hold the revision being countered — this method
     * exists for the common case of proposing from the item/side/user instead.
     */
    public function propose(
        TransferRequisitionItem $item,
        User $user,
        NegotiationSide $side,
        string $unitName,
        int $unitRatio,
        int $qty,
        ?int $substituteProductVariantId = null,
        ?string $reason = null,
        ?TransferRequisitionItemRevision $respondsTo = null,
    ): TransferRequisitionItemRevision {
        $attributes = [
            'user_id' => $user->id,
            'product_variant_id' => $item->product_variant_id,
            'substitute_product_variant_id' => $substituteProductVariantId,
            'proposed_unit_name' => $unitName,
            'proposed_unit_ratio' => $unitRatio,
            'proposed_qty' => $qty,
            'proposed_base_qty' => $qty * $unitRatio,
            'negotiation_reason' => $reason,
            'side' => $side,
        ];

        if ($respondsTo !== null) {
            return $respondsTo->counterWith($attributes);
        }

        return DB::transaction(function () use ($item, $attributes) {
            return TransferRequisitionItemRevision::create(array_merge($attributes, [
                'transfer_requisition_item_id' => $item->id,
                'status' => RevisionStatus::Pending,
            ]));
        });
    }

    /**
     * Accept a revision, syncing its proposed values onto the parent item.
     * Delegates to TransferRequisitionItemRevision::accept(), which already
     * wraps this in its own transaction with a row lock on the item.
     */
    public function accept(TransferRequisitionItemRevision $revision): void
    {
        $this->assertNegotiable($revision, 'accept');

        DB::transaction(function () use ($revision) {
            $revision->accept();

            $revision->item()->update([
                'approved_unit_name' => $revision->proposed_unit_name,
                'approved_unit_ratio' => $revision->proposed_unit_ratio,
                'approved_qty' => $revision->proposed_qty,
                'approved_base_qty' => $revision->proposed_base_qty,
                'substitute_product_variant_id' => $revision->substitute_product_variant_id,
            ]);
        });
    }

    public function reject(TransferRequisitionItemRevision $revision): void
    {
        $this->assertNegotiable($revision, 'reject');

        DB::transaction(function () use ($revision) {
            $revision->reject();
        });
    }

    /**
     * Counter a pending revision with a new proposal from the opposite side.
     */
    public function counter(
        TransferRequisitionItemRevision $revision,
        User $user,
        string $unitName,
        int $unitRatio,
        int $qty,
        ?int $substituteProductVariantId = null,
        ?string $reason = null,
    ): TransferRequisitionItemRevision {
        $this->assertNegotiable($revision, 'counter');

        return $revision->counterWith([
            'user_id' => $user->id,
            'product_variant_id' => $revision->product_variant_id,
            'substitute_product_variant_id' => $substituteProductVariantId,
            'proposed_unit_name' => $unitName,
            'proposed_unit_ratio' => $unitRatio,
            'proposed_qty' => $qty,
            'proposed_base_qty' => $qty * $unitRatio,
            'negotiation_reason' => $reason,
            'side' => $revision->side->opposite(),
        ]);
    }

    /**
     * Materialize requested quantities as approved for items that have not
     * been negotiated (approved_base_qty is null). Called by ConfirmAction
     * before transitioning to Confirmed status.
     */
    public function materializeRequestedAsApproved(TransferRequisition $requisition): void
    {
        DB::transaction(function () use ($requisition) {
            $requisition->items()
                ->whereNull('approved_base_qty')
                ->each(function (TransferRequisitionItem $item) {
                    $item->update([
                        'approved_unit_name' => $item->requested_unit_name,
                        'approved_unit_ratio' => $item->requested_unit_ratio,
                        'approved_qty' => $item->requested_qty,
                        'approved_base_qty' => $item->requested_base_qty,
                    ]);
                });
        });
    }
}
