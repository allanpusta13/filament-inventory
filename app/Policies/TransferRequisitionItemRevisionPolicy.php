<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TransferRequisitionItemRevision;
use App\Models\User;

class TransferRequisitionItemRevisionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isAuditor() || $user->isBranchManager() || $user->isWarehouseStaff();
    }

    public function view(User $user, TransferRequisitionItemRevision $revision): bool
    {
        if ($user->isAdmin() || $user->isAuditor()) {
            return true;
        }

        if ($user->isBranchManager() || $user->isWarehouseStaff()) {
            $requisition = $revision->transferRequisitionItem->transferRequisition;

            return $user->canAccessWarehouse($requisition->fromWarehouse)
                || $user->canAccessWarehouse($requisition->toWarehouse);
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isBranchManager() || $user->isWarehouseStaff();
    }

    public function update(User $user, TransferRequisitionItemRevision $revision): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isBranchManager() || $user->isWarehouseStaff()) {
            $requisition = $revision->transferRequisitionItem->transferRequisition;

            return $user->canAccessWarehouse($requisition->fromWarehouse)
                || $user->canAccessWarehouse($requisition->toWarehouse);
        }

        return false;
    }

    public function delete(User $user, TransferRequisitionItemRevision $revision): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isBranchManager() || $user->isWarehouseStaff()) {
            $requisition = $revision->transferRequisitionItem->transferRequisition;

            return $user->canAccessWarehouse($requisition->fromWarehouse)
                || $user->canAccessWarehouse($requisition->toWarehouse);
        }

        return false;
    }

    public function restore(User $user, TransferRequisitionItemRevision $revision): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isBranchManager() || $user->isWarehouseStaff()) {
            $requisition = $revision->transferRequisitionItem->transferRequisition;

            return $user->canAccessWarehouse($requisition->fromWarehouse)
                || $user->canAccessWarehouse($requisition->toWarehouse);
        }

        return false;
    }

    public function forceDelete(User $user, TransferRequisitionItemRevision $revision): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function restoreAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
