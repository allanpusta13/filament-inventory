<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RevisionStatus;
use App\Enums\TransferRequisitionStatus;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItemRevision;
use DomainException;

class NegotiationService
{
    public function submitRequest(TransferRequisition $requisition): void
    {
        if ($requisition->status !== TransferRequisitionStatus::Draft) {
            throw new DomainException('Only draft requisitions can be submitted.');
        }

        $requisition->update([
            'status' => TransferRequisitionStatus::Requested,
            'requested_at' => now(),
            'requested_by' => $requisition->requested_by ?? auth()->id(),
        ]);
    }

    /**
     * Materialize requested items as approved items on confirm.
     *
     * After materialization, verifies every item has a non-null approved
     * base quantity — a confirm with a null approved qty would silently
     * produce a wrong reservation.
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

        if ($requisition->items()->whereNull('approved_base_qty')->exists()) {
            throw new DomainException(
                'All items must have an approved base quantity before confirmation.'
            );
        }
    }

    public function assertNegotiable(TransferRequisitionItemRevision $revision): void
    {
        $parent = $revision->item->transferRequisition;
        if (! in_array($parent->status, [
            TransferRequisitionStatus::Requested,
            TransferRequisitionStatus::UnderReviewFulfiller,
            TransferRequisitionStatus::UnderReviewRequestor,
        ], true)) {
            throw new \App\Exceptions\NegotiationNotAllowedException(
                'Parent requisition is not in a negotiable status.'
            );
        }
        if ($revision->status !== RevisionStatus::Pending) {
            throw new \App\Exceptions\NegotiationNotAllowedException(
                'Revision is already resolved.'
            );
        }
    }

    public function accept(TransferRequisitionItemRevision $revision): void
    {
        $this->assertNegotiable($revision);
        $revision->ensureCanTransitionTo(RevisionStatus::Accepted);
        $revision->update([
            'status' => RevisionStatus::Accepted,
            'responded_at' => now(),
        ]);
    }

    public function reject(TransferRequisitionItemRevision $revision): void
    {
        $this->assertNegotiable($revision);
        $revision->ensureCanTransitionTo(RevisionStatus::Rejected);
        $revision->update([
            'status' => RevisionStatus::Rejected,
            'responded_at' => now(),
        ]);
    }
}
