<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\InTransit;
use App\Models\User;

class InTransitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isAuditor() || $user->isBranchManager() || $user->isWarehouseStaff();
    }

    public function view(User $user, InTransit $inTransit): bool
    {
        if ($user->isAdmin() || $user->isAuditor()) {
            return true;
        }

        if ($user->isBranchManager() || $user->isWarehouseStaff()) {
            return $user->canAccessWarehouse($inTransit->transferRequisition->fromWarehouse)
                || $user->canAccessWarehouse($inTransit->transferRequisition->toWarehouse);
        }

        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, InTransit $inTransit): bool
    {
        return false;
    }

    public function delete(User $user, InTransit $inTransit): bool
    {
        return false;
    }

    public function restore(User $user, InTransit $inTransit): bool
    {
        return false;
    }

    public function forceDelete(User $user, InTransit $inTransit): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function receive(User $user, InTransit $inTransit): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isBranchManager() || $user->isWarehouseStaff()) {
            return $user->canAccessWarehouse($inTransit->transferRequisition->fromWarehouse)
                || $user->canAccessWarehouse($inTransit->transferRequisition->toWarehouse);
        }

        return false;
    }
}
