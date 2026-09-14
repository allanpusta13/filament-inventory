<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\TransferRequisitionStatus;
use App\Models\TransferRequisition;
use App\Models\User;

class TransferRequisitionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TransferRequisition $requisition): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, TransferRequisition $requisition): bool
    {
        return true;
    }

    public function delete(User $user, TransferRequisition $requisition): bool
    {
        return in_array($requisition->status, [
            TransferRequisitionStatus::Draft,
            TransferRequisitionStatus::Cancelled,
        ], true);
    }

    public function restore(User $user, TransferRequisition $requisition): bool
    {
        return true;
    }

    public function forceDelete(User $user, TransferRequisition $requisition): bool
    {
        return $user->isAdmin();
    }

    public function confirm(User $user, TransferRequisition $requisition): bool
    {
        return in_array($requisition->status, [
            TransferRequisitionStatus::Requested,
            TransferRequisitionStatus::UnderReviewFulfiller,
            TransferRequisitionStatus::UnderReviewRequestor,
        ], true);
    }

    public function dispatch(User $user, TransferRequisition $requisition): bool
    {
        return $requisition->status === TransferRequisitionStatus::Confirmed;
    }

    public function receive(User $user, TransferRequisition $requisition): bool
    {
        return in_array($requisition->status, [
            TransferRequisitionStatus::Dispatched,
            TransferRequisitionStatus::PartiallyReceived,
        ], true);
    }

    public function cancel(User $user, TransferRequisition $requisition): bool
    {
        return in_array($requisition->status, [
            TransferRequisitionStatus::Draft,
            TransferRequisitionStatus::Requested,
            TransferRequisitionStatus::UnderReviewFulfiller,
            TransferRequisitionStatus::UnderReviewRequestor,
            TransferRequisitionStatus::Confirmed,
        ], true);
    }
}