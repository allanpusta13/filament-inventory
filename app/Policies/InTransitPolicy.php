<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\InTransit;
use App\Models\User;

/**
 * InTransit policy — §8.4. Read-only.
 *
 * ⚠ BranchManager: same tier as WarehouseStaff — must touch either
 * endpoint of the parent requisition.
 */
class InTransitPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, InTransit $t): bool
    {
        return $user->isAdmin()
            || $user->isAuditor()
            || $user->warehouses->contains($t->transferRequisition->from_warehouse_id)
            || $user->warehouses->contains($t->transferRequisition->to_warehouse_id);
    }
}
