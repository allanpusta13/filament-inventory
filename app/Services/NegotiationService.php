<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NegotiationSide;
use App\Enums\RevisionStatus;
use App\Enums\TransferRequisitionStatus;
use App\Exceptions\DomainRuleViolationException;
use App\Exceptions\InvalidDocumentStateException;
use App\Exceptions\NegotiationNotAllowedException;
use App\Models\ProductVariantUnitConversion;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use App\Models\TransferRequisitionItemRevision;
use Illuminate\Support\Facades\DB;

/**
 * NegotiationService — the requisition negotiation loop.
 *
 * Blueprint §6.3. Owns the submit / negotiate / accept / reject flow of
 * a transfer requisition, plus the confirm-time materialization step
 * that copies requested quantities into the approved leg.
 *
 * Public methods:
 *   - submitRequest()                    — Draft → Requested
 *   - materializeRequestedAsApproved()   — requested → approved copy
 *   - assertNegotiable()                 — shared pre-flight guard
 *   - accept()                           — resolve a revision as Accepted
 *   - reject()                           — resolve a revision as Rejected
 *   - submitRevision()                   — author a new negotiation revision
 *
 * Ping-pong rule (§6.3): a revision submitted by the fulfiller moves the
 * parent to `UnderReviewRequestor`; a revision submitted by the
 * requestor moves the parent to `UnderReviewFulfiller`. This preserves
 * the `UnderReviewFulfiller ⇌ UnderReviewRequestor` state machine.
 *
 * All public methods except `assertNegotiable()` run inside a
 * transaction with `lockForUpdate()` on the parent requisition, its
 * items, and (when relevant) the revision row — matching the locking
 * discipline in §6.2.
 */
class NegotiationService
{
    /**
     * Submit a Draft requisition for review.
     *
     * §6.3: only Draft → Requested is legal. Any other status throws
     * InvalidDocumentStateException.
     */
    public function submitRequest(TransferRequisition $requisition): void
    {
        DB::transaction(function () use ($requisition) {
            $fresh = TransferRequisition::lockForUpdate()->findOrFail($requisition->id);

            if ($fresh->status !== TransferRequisitionStatus::Draft) {
                throw new InvalidDocumentStateException(
                    documentType: TransferRequisition::class,
                    documentId: (int) $fresh->id,
                    actualStatus: $fresh->status->value,
                    action: 'submit',
                );
            }

            $fresh->update([
                'status' => TransferRequisitionStatus::Requested,
                'requested_at' => now(),
                'requested_by' => $fresh->requested_by ?? auth()->id(),
            ]);
        });
    }

    /**
     * Materialize requested items as approved items on confirm.
     *
     * Invariant: after this method returns, every item has a non-null
     * `approved_base_qty`. The loop copies `requested_base_qty` (NOT NULL by
     * schema) onto `approved_base_qty` unconditionally whenever the item's
     * approved qty is still null, so the post-loop state is guaranteed on
     * every schema-valid insert. The prior post-loop defensive guard was
     * therefore unreachable and has been removed.
     *
     * Called by `TransferRequisitionService::confirm()` (§6.6) inside the
     * confirm transaction, after items have been locked and bound to the
     * parent relation.
     */
    public function materializeRequestedAsApproved(TransferRequisition $requisition): void
    {
        foreach ($requisition->items as $item) {
            if ($item->approved_base_qty !== null) {
                continue;
            }

            $item->update([
                'approved_unit_name' => $item->requested_unit_name,
                'approved_unit_ratio' => $item->requested_unit_ratio,
                'approved_qty' => $item->requested_qty,
                'approved_base_qty' => $item->requested_base_qty,
            ]);
        }
    }

    /**
     * Shared pre-flight guard for accept / reject / submit.
     *
     * §6.3: the parent requisition must be in a negotiable status
     * (Requested | UnderReviewFulfiller | UnderReviewRequestor) and the
     * revision must still be Pending.
     *
     * Reads `$revision->item->transferRequisition` — the caller is
     * responsible for binding the locked parent relation before calling
     * (see `accept()` / `reject()` which `setRelation()` on the locked
     * rows).
     */
    public function assertNegotiable(TransferRequisitionItemRevision $revision): void
    {
        $parent = $revision->item->transferRequisition;
        if (! in_array($parent->status, [
            TransferRequisitionStatus::Requested,
            TransferRequisitionStatus::UnderReviewFulfiller,
            TransferRequisitionStatus::UnderReviewRequestor,
        ], true)) {
            throw new NegotiationNotAllowedException(
                'errors.negotiation_not_allowed',
                ['requisition' => (int) $parent->id, 'status' => $parent->status->value],
            );
        }
        if ($revision->isResolved()) {
            throw new NegotiationNotAllowedException(
                'errors.revision_already_resolved',
                ['revision' => (int) $revision->id],
            );
        }
    }

    /**
     * Resolve a revision as Accepted.
     *
     * §6.3: locks the revision, the parent item, and the parent
     * requisition; binds the locked relations so `assertNegotiable()`
     * reads locked state; transitions the revision to Accepted.
     */
    public function accept(TransferRequisitionItemRevision $revision): void
    {
        DB::transaction(function () use ($revision) {
            $freshRevision = TransferRequisitionItemRevision::query()
                ->lockForUpdate()
                ->findOrFail($revision->id);

            $freshItem = TransferRequisitionItem::query()
                ->lockForUpdate()
                ->findOrFail($freshRevision->transfer_requisition_item_id);

            $parent = TransferRequisition::query()
                ->lockForUpdate()
                ->findOrFail($freshItem->transfer_requisition_id);

            // Bind the locked rows so the negotiability checks below
            // operate on locked state instead of unlocked lazy-loaded copies.
            $freshItem->setRelation('transferRequisition', $parent);
            $freshRevision->setRelation('item', $freshItem);

            $this->assertNegotiable($freshRevision);
            $freshRevision->ensureCanTransitionTo(RevisionStatus::Accepted);
            $freshRevision->update([
                'status' => RevisionStatus::Accepted,
                'responded_at' => now(),
            ]);
        });
    }

    /**
     * Resolve a revision as Rejected.
     *
     * §6.3: same lock / bind / guard discipline as `accept()`, with
     * target status Rejected.
     */
    public function reject(TransferRequisitionItemRevision $revision): void
    {
        DB::transaction(function () use ($revision) {
            $freshRevision = TransferRequisitionItemRevision::query()
                ->lockForUpdate()
                ->findOrFail($revision->id);

            $freshItem = TransferRequisitionItem::query()
                ->lockForUpdate()
                ->findOrFail($freshRevision->transfer_requisition_item_id);

            $parent = TransferRequisition::query()
                ->lockForUpdate()
                ->findOrFail($freshItem->transfer_requisition_id);

            // Bind the locked rows so the negotiability checks below
            // operate on locked state instead of unlocked lazy-loaded copies.
            $freshItem->setRelation('transferRequisition', $parent);
            $freshRevision->setRelation('item', $freshItem);

            $this->assertNegotiable($freshRevision);
            $freshRevision->ensureCanTransitionTo(RevisionStatus::Rejected);
            $freshRevision->update([
                'status' => RevisionStatus::Rejected,
                'responded_at' => now(),
            ]);
        });
    }

    /**
     * Submit a new negotiation revision for a requisition item.
     *
     * The proposed unit must be sourced from the effective variant's own
     * conversion rows (Phase 09: substitute-variant unit sourcing) — the
     * ratio is resolved server-side, never trusted from the form.
     *
     * The parent requisition status is transitioned to the opposite
     * side's review state on revision submission: a revision submitted by
     * the fulfiller moves the parent to `UnderReviewRequestor`; a revision
     * submitted by the requestor moves the parent to
     * `UnderReviewFulfiller`. This preserves the
     * `UnderReviewFulfiller ⇌ UnderReviewRequestor` ping-pong lifecycle.
     */
    public function submitRevision(
        TransferRequisitionItem $item,
        ?int $substituteVariantId,
        NegotiationSide $side,
        string $proposedUnitName,
        int $proposedQty,
        ?string $negotiationReason = null,
        ?int $respondsToRevisionId = null,
    ): TransferRequisitionItemRevision {
        return DB::transaction(function () use (
            $item, $substituteVariantId, $side,
            $proposedUnitName, $proposedQty,
            $negotiationReason, $respondsToRevisionId
        ) {
            $freshItem = TransferRequisitionItem::query()
                ->lockForUpdate()
                ->findOrFail($item->id);

            $parent = TransferRequisition::query()
                ->lockForUpdate()
                ->findOrFail($freshItem->transfer_requisition_id);

            if (! in_array($parent->status, [
                TransferRequisitionStatus::Requested,
                TransferRequisitionStatus::UnderReviewFulfiller,
                TransferRequisitionStatus::UnderReviewRequestor,
            ], true)) {
                throw new NegotiationNotAllowedException(
                    'errors.negotiation_not_allowed',
                    ['requisition' => (int) $parent->id, 'status' => $parent->status->value],
                );
            }

            if ($proposedQty < 1) {
                throw new DomainRuleViolationException('errors.invalid_proposed_quantity', [
                    'qty' => $proposedQty,
                ]);
            }

            $effectiveVariantId = $substituteVariantId ?? $freshItem->product_variant_id;

            $proposedUnitRatio = ProductVariantUnitConversion::where(
                'product_variant_id',
                $effectiveVariantId
            )
                ->where('unit_name', $proposedUnitName)
                ->value('base_unit_ratio');

            if ($proposedUnitRatio === null) {
                throw new DomainRuleViolationException('errors.undefined_unit', [
                    'unit' => $proposedUnitName,
                    'variant' => $effectiveVariantId,
                ]);
            }

            if ($respondsToRevisionId !== null) {
                $respondsTo = TransferRequisitionItemRevision::query()
                    ->findOrFail($respondsToRevisionId);

                if ((int) $respondsTo->transfer_requisition_item_id !== (int) $freshItem->id) {
                    throw new DomainRuleViolationException('errors.cross_item_revision', [
                        'revision' => $respondsToRevisionId,
                        'item' => (int) $freshItem->id,
                    ]);
                }
            }

            $revision = TransferRequisitionItemRevision::create([
                'transfer_requisition_item_id' => $freshItem->id,
                'user_id' => auth()->id(),
                'product_variant_id' => $freshItem->product_variant_id,
                'substitute_product_variant_id' => $substituteVariantId,
                'proposed_unit_name' => $proposedUnitName,
                'proposed_unit_ratio' => $proposedUnitRatio,
                'proposed_qty' => $proposedQty,
                'proposed_base_qty' => $proposedQty * (int) $proposedUnitRatio,
                'negotiation_reason' => $negotiationReason,
                'side' => $side,
                'status' => RevisionStatus::Pending,
                'responds_to_revision_id' => $respondsToRevisionId,
            ]);

            // Opposite-side review transition (owner decision): the
            // submitting side proposes, the counterpart reviews next.
            // Already in the target state → no-op.
            $targetStatus = $side === NegotiationSide::Fulfiller
                ? TransferRequisitionStatus::UnderReviewRequestor
                : TransferRequisitionStatus::UnderReviewFulfiller;

            if ($parent->status !== $targetStatus) {
                $parent->update(['status' => $targetStatus]);
            }

            return $revision;
        });
    }
}
