<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TransferRequisitionStatus;
use App\Exceptions\DomainRuleViolationException;
use App\Exceptions\InvalidDocumentStateException;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use Illuminate\Support\Facades\DB;

/**
 * TransferRequisitionService — requisition lifecycle boundary.
 *
 * Blueprint §6.6. Owns the two state transitions that are not owned by
 * `NegotiationService` (§6.3) or `InventoryService` (§6.2):
 *
 *   - confirm()           — Requested | UnderReviewFulfiller |
 *                           UnderReviewRequestor → Confirmed. Delegates
 *                           requested → approved materialization to
 *                           `NegotiationService::materializeRequestedAsApproved()`.
 *   - cancelRequisition() — any cancellable state → Cancelled.
 *
 * Both methods run inside a transaction with `lockForUpdate()` on the
 * parent requisition and its items, matching the locking discipline in
 * §6.2 and §6.3. Events are dispatched inside the transaction; their
 * `ShouldDispatchAfterCommit` marker (§22.1a) ensures after-commit
 * delivery.
 *
 * The `cancel` legality boundary lives on the model
 * (`TransferRequisition::canBeCancelled()` §3.7) — this service
 * re-checks it under the lock before mutating.
 */
class TransferRequisitionService
{
    public function __construct(
        private readonly NegotiationService $negotiation,
    ) {}

    /**
     * Confirm a negotiated requisition and materialize the approved leg.
     *
     * §6.6 / §19.1: atomic — the materialization of requested → approved
     * and the parent transition to Confirmed happen inside one transaction
     * with the parent, items, and (via the materializer) each item's
     * approved leg locked.
     */
    public function confirm(TransferRequisition $requisition): void
    {
        // Canonical boundary (§19.1): the event is dispatched INSIDE the
        // transaction. After-commit delivery is guaranteed by
        // `ShouldDispatchAfterCommit` on the event (§22.1a/§22.2).
        DB::transaction(function () use ($requisition) {
            $fresh = TransferRequisition::query()
                ->lockForUpdate()
                ->findOrFail($requisition->id);

            if (! in_array($fresh->status, [
                TransferRequisitionStatus::Requested,
                TransferRequisitionStatus::UnderReviewFulfiller,
                TransferRequisitionStatus::UnderReviewRequestor,
            ], true)) {
                throw new InvalidDocumentStateException(
                    documentType: TransferRequisition::class,
                    documentId: (int) $fresh->id,
                    actualStatus: $fresh->status->value,
                    action: 'confirm',
                );
            }

            // The locked item collection is bound to the parent relation so
            // materializeRequestedAsApproved() operates on locked rows
            // instead of lazy-loading unlocked copies.
            $items = TransferRequisitionItem::query()
                ->where('transfer_requisition_id', $fresh->id)
                ->lockForUpdate()
                ->get();

            // The form requires at least one manifest item (§7B.1): never
            // confirm an item-less requisition.
            if ($items->isEmpty()) {
                throw new DomainRuleViolationException('errors.empty_requisition_items');
            }

            $fresh->setRelation('items', $items);

            $this->negotiation->materializeRequestedAsApproved($fresh);

            $fresh->update([
                'status' => TransferRequisitionStatus::Confirmed,
                'approved_at' => now(),
                'approved_by' => auth()->id(),
            ]);

            event(new \App\Events\TransferConfirmed($fresh->id));
        });
    }

    /**
     * Cancel a requisition.
     *
     * §6.6 / §19.2: allowed only while `TransferRequisition::canBeCancelled()`
     * returns true (five pre-dispatch states, §3.7). Re-checks under the
     * parent lock before mutating.
     */
    public function cancelRequisition(TransferRequisition $requisition): void
    {
        // Canonical boundary (§19.2): the event is dispatched INSIDE the
        // transaction. After-commit delivery is guaranteed by
        // `ShouldDispatchAfterCommit` on the event (§22.1a/§22.2).
        DB::transaction(function () use ($requisition) {
            $fresh = TransferRequisition::query()
                ->lockForUpdate()
                ->findOrFail($requisition->id);

            if (! $fresh->canBeCancelled()) {
                throw new InvalidDocumentStateException(
                    documentType: TransferRequisition::class,
                    documentId: (int) $fresh->id,
                    actualStatus: $fresh->status->value,
                    action: 'cancel',
                );
            }

            $fresh->update([
                'status' => TransferRequisitionStatus::Cancelled,
            ]);

            event(new \App\Events\TransferCancelled($fresh->id));
        });
    }
}
