<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Warehouse;

class WarehousePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isAuditor();
    }

    public function view(User $user, Warehouse $w): bool
    {
        return $user->isAdmin()
            || $user->isAuditor()
            || $user->warehouses->contains($w->id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Warehouse $w): bool
    {
        return $user->isAdmin();
    }

    /**
     * A warehouse may only be deleted when it has no ledger history and is
     * not referenced by any document (PO, SO, TR, or direct transfer).
     * This prevents raw FK violations from bubbling up as 500s.
     */
    public function delete(User $user, Warehouse $w): bool
    {
        if (! $user->isAdmin()) {
            return false;
        }

        if ($w->stockMovements()->exists()) {
            return false;
        }

        if ($w->purchaseOrders()->exists()) {
            return false;
        }

        if ($w->salesOrders()->exists()) {
            return false;
        }

        if ($w->transferRequisitionsFrom()->exists()) {
            return false;
        }

        if ($w->transferRequisitionsTo()->exists()) {
            return false;
        }

        if ($w->directTransfersFrom()->exists()) {
            return false;
        }

        if ($w->directTransfersTo()->exists()) {
            return false;
        }

        return true;
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
