<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StockMovement;
use App\Models\User;

/**
 * StockMovement policy — §8.5. Read-only; no create/update/delete.
 *
 * ⚠ BranchManager: same tier as WarehouseStaff — must be assigned to
 * the movement's warehouse. Audit filters remain admin/auditor only.
 */
class StockMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, StockMovement $m): bool
    {
        return $user->isAdmin()
            || $user->isAuditor()
            || $user->warehouses->contains($m->warehouse_id);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, StockMovement $m): bool
    {
        return false;
    }

    public function delete(User $user, StockMovement $m): bool
    {
        return false;
    }

    /**
     * `$modelClass` is the `Model::class` string forwarded by the call
     * site (`->can('viewAuditFilters', Model::class)`): Laravel resolves
     * the owning policy from that class-string and passes it through as
     * the second argument. The decision itself is role-only (A8).
     *
     * ⚠ BranchManager is NOT granted audit-filter authority.
     */
    public function viewAuditFilters(User $user): bool
    {
        return $user->isAdmin() || $user->isAuditor();
    }

    // NOTE: createDirectTransfer() removed — see DirectTransferPolicy::create().
}
