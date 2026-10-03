<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LossLedger;
use App\Models\User;

/**
 * LossLedger policy — §8.6. Read-only.
 *
 * ⚠ BranchManager: same tier as WarehouseStaff — must be assigned to
 * the loss's warehouse. Audit filters remain admin/auditor only.
 */
class LossLedgerPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, LossLedger $l): bool
    {
        return $user->isAdmin()
            || $user->isAuditor()
            || $user->warehouses->contains($l->warehouse_id);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, LossLedger $l): bool
    {
        return false;
    }

    public function delete(User $user, LossLedger $l): bool
    {
        return false;
    }

    /**
     * ⚠ BranchManager is NOT granted audit-filter authority.
     */
    public function viewAuditFilters(User $user): bool
    {
        return $user->isAdmin() || $user->isAuditor();
    }
}
