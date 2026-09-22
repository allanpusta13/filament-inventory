<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Warehouse;

class WarehousePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Warehouse $warehouse): bool
    {
        return $user->isAdmin() || $user->isAuditor() || (($user->isBranchManager() || $user->isWarehouseStaff()) && $user->canAccessWarehouse($warehouse));
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return $user->isAdmin() || (($user->isBranchManager() || $user->isWarehouseStaff()) && $user->canAccessWarehouse($warehouse));
    }

    public function delete(User $user, Warehouse $warehouse): bool
    {
        return false;
    }

    public function restore(User $user, Warehouse $warehouse): bool
    {
        return false;
    }

    public function forceDelete(User $user, Warehouse $warehouse): bool
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

    public function adjustStock(User $user, Warehouse $warehouse): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isBranchManager() || $user->isWarehouseStaff()) {
            return $user->canAccessWarehouse($warehouse);
        }

        return false;
    }

    public function recordLoss(User $user, Warehouse $warehouse): bool
    {
        if ($user->isAdmin() || $user->isAuditor()) {
            return true;
        }

        if ($user->isBranchManager() || $user->isWarehouseStaff()) {
            return $user->canAccessWarehouse($warehouse);
        }

        return false;
    }
}
