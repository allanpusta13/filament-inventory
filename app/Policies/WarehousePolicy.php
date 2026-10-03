<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Warehouse;

/**
 * Warehouse policy — §8.11. Admin-only management; warehouse-scoped read.
 *
 * ⚠ BranchManager: read access follows the same tier as WarehouseStaff
 * (assigned warehouses only). Warehouse creation / update / deletion
 * remain admin-only. The delete reference guards are admin-only.
 */
class WarehousePolicy
{
    public function viewAny(User $user): bool
    {
        // Warehouse staff may list only their assigned warehouses — the
        // database-level restriction lives in the resource query scope
        // (§20.1). `viewAny` must therefore pass for staff with assignments.
        return $user->isAdmin()
            || $user->isAuditor()
            || $user->warehouses()->exists();
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
     * not referenced by any document (PO, SO, TR, or direct transfer) and
     * has no loss ledger rows.
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

        if ($w->lossLedgers()->exists()) {
            return false;
        }

        return true;
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
