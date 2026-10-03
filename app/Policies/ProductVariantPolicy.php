<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProductVariant;
use App\Models\User;

/**
 * ProductVariant policy — §8.2.
 *
 * ⚠ BranchManager is not granted catalog-management authority (create /
 * update / delete remain admin-only). For `adjustStock`, BranchManager
 * falls into the "non-auditor operational user with at least one
 * warehouse assignment" tier — same as WarehouseStaff — matching the
 * blueprint's intent that adjustments are an operational duty, not a
 * catalog operation.
 */
class ProductVariantPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ProductVariant $variant): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, ProductVariant $variant): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, ProductVariant $variant): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, ProductVariant $variant): bool
    {
        return $user->isAdmin();
    }

    public function restoreAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, ProductVariant $variant): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    /**
     * Manual stock adjustments (QuickStockAdjustmentAction) are an
     * operational duty, not catalog management: non-auditor warehouse
     * staff with at least one warehouse assignment may adjust (the
     * action's warehouse select is scoped to assigned warehouses),
     * while catalog create/update/delete stay admin-only above.
     * Auditors are read-only and never mutate stock, even when assigned
     * to warehouses (§8.3, §8.7, §8.8).
     * Per A8, this role distinction lives here — never in ->visible().
     *
     * ⚠ BranchManager: falls into the non-auditor-with-assignment tier
     * alongside WarehouseStaff. Owner decision on distinct BranchManager
     * operational abilities is pending.
     */
    public function adjustStock(User $user, ProductVariant $variant): bool
    {
        return $user->isAdmin()
            || (! $user->isAuditor() && $user->warehouses()->exists());
    }
}
