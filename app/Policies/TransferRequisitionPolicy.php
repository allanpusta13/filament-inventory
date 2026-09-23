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
        return $user->isAdmin() || $user->isAuditor() || $user->isBranchManager() || $user->isWarehouseStaff();
    }

    public function view(User $user, TransferRequisition $requisition): bool
    {
        if ($user->isAdmin() || $user->isAuditor()) {
            return true;
        }

        if ($user->isBranchManager() || $user->isWarehouseStaff()) {
            return $user->canAccessWarehouse($requisition->fromWarehouse)
                || $user->canAccessWarehouse($requisition->toWarehouse);
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isBranchManager() || $user->isWarehouseStaff();
    }

    public function update(User $user, TransferRequisition $requisition): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isBranchManager() || $user->isWarehouseStaff()) {
            return ($user->canAccessWarehouse($requisition->fromWarehouse)
                    || $user->canAccessWarehouse($requisition->toWarehouse))
                && $requisition->status === TransferRequisitionStatus::Draft;
        }

        return false;
    }

    public function delete(User $user, TransferRequisition $requisition): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isBranchManager() || $user->isWarehouseStaff()) {
            return ($user->canAccessWarehouse($requisition->fromWarehouse)
                    || $user->canAccessWarehouse($requisition->toWarehouse))
                && in_array($requisition->status, [
                    TransferRequisitionStatus::Draft,
                    TransferRequisitionStatus::Cancelled,
                ], true);
        }

        return false;
    }

    public function restore(User $user, TransferRequisition $requisition): bool
    {
        return $user->isAdmin() || $user->isAuditor();
    }

    public function forceDelete(User $user, TransferRequisition $requisition): bool
    {
        return $user->isAdmin();
    }

    public function confirm(User $user, TransferRequisition $requisition): bool
    {
        if ($user->isAdmin() || $user->isAuditor()) {
            return true;
        }

        if ($user->isBranchManager() || $user->isWarehouseStaff()) {
            return ($user->canAccessWarehouse($requisition->fromWarehouse)
                    || $user->canAccessWarehouse($requisition->toWarehouse))
                && in_array($requisition->status, [
                    TransferRequisitionStatus::Requested,
                    TransferRequisitionStatus::UnderReviewFulfiller,
                    TransferRequisitionStatus::UnderReviewRequestor,
                ], true);
        }

        return false;
    }

    public function dispatch(User $user, TransferRequisition $requisition): bool
    {
        if ($user->isAdmin() || $user->isAuditor()) {
            return true;
        }

        if ($user->isBranchManager() || $user->isWarehouseStaff()) {
            return ($user->canAccessWarehouse($requisition->fromWarehouse)
                    || $user->canAccessWarehouse($requisition->toWarehouse))
                && $requisition->status === TransferRequisitionStatus::Confirmed;
        }

        return false;
    }

    public function receive(User $user, TransferRequisition $requisition): bool
    {
        if ($user->isAdmin() || $user->isAuditor()) {
            return true;
        }

        if ($user->isBranchManager() || $user->isWarehouseStaff()) {
            return ($user->canAccessWarehouse($requisition->fromWarehouse)
                    || $user->canAccessWarehouse($requisition->toWarehouse))
                && in_array($requisition->status, [
                    TransferRequisitionStatus::Dispatched,
                    TransferRequisitionStatus::PartiallyReceived,
                ], true);
        }

        return false;
    }

    public function cancel(User $user, TransferRequisition $requisition): bool
    {
        $allowedStatuses = [
            TransferRequisitionStatus::Draft,
            TransferRequisitionStatus::Requested,
            TransferRequisitionStatus::UnderReviewFulfiller,
            TransferRequisitionStatus::UnderReviewRequestor,
            TransferRequisitionStatus::Confirmed,
        ];

        if (! in_array($requisition->status, $allowedStatuses, true)) {
            return false;
        }

        if ($user->isAdmin() || $user->isAuditor()) {
            return true;
        }

        if ($user->isBranchManager() || $user->isWarehouseStaff()) {
            return $user->canAccessWarehouse($requisition->fromWarehouse)
                || $user->canAccessWarehouse($requisition->toWarehouse);
        }

        return false;
    }

    public function negotiate(User $user, TransferRequisition $requisition): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isBranchManager() || $user->isWarehouseStaff()) {
            return ($user->canAccessWarehouse($requisition->fromWarehouse)
                    || $user->canAccessWarehouse($requisition->toWarehouse))
                && in_array($requisition->status, [
                    TransferRequisitionStatus::Requested,
                    TransferRequisitionStatus::UnderReviewFulfiller,
                    TransferRequisitionStatus::UnderReviewRequestor,
                ], true);
        }

        return false;
    }

    public function recordLoss(User $user, TransferRequisition $requisition): bool
    {
        if ($user->isAdmin() || $user->isAuditor()) {
            return true;
        }

        if ($user->isBranchManager() || $user->isWarehouseStaff()) {
            return ($user->canAccessWarehouse($requisition->fromWarehouse)
                    || $user->canAccessWarehouse($requisition->toWarehouse))
                && in_array($requisition->status, [
                    TransferRequisitionStatus::Dispatched,
                    TransferRequisitionStatus::PartiallyReceived,
                ], true);
        }

        return false;
    }

    /**
     * [Phase 4 / Principle A8] Sole source of truth for admin/auditor-only
     * review-surface visibility (cross-warehouse filters, StatsOverview).
     * Relocated verbatim from Filament visible() closures. FROZEN except
     * for a genuinely new ability or a demonstrated bug.
     */
    public function viewAdminReview(User $user): bool
    {
        return $user->isAdmin() || $user->isAuditor();
    }
}
