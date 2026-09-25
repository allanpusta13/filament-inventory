<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TransferRequisition;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * TransferRequisitionService handles high-level operations on TransferRequisition
 * that don't fit neatly into InventoryService or NegotiationService.
 *
 * Per blueprint §6, this service is not explicitly listed but is needed for
 * the TransferRequisitionsTable cancel action.
 */
class TransferRequisitionService
{
    /**
     * Cancel a transfer requisition.
     *
     * This operation is only allowed when the requisition is in a cancellable
     * status (Draft, Requested, UnderReviewFulfiller, UnderReviewRequestor, Confirmed)
     * per the model's canBeCancelled() method.
     */
    public function cancelRequisition(TransferRequisition $requisition): void
    {
        if (! $requisition->canBeCancelled()) {
            throw new RuntimeException('Transfer requisition cannot be cancelled in its current status.');
        }

        DB::transaction(function () use ($requisition) {
            $requisition->update([
                'status' => \App\Enums\TransferRequisitionStatus::Cancelled,
            ]);
        });
    }
}
