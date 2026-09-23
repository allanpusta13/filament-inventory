<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StockMovement;
use App\Models\User;

class StockMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isAuditor() || $user->isBranchManager() || $user->isWarehouseStaff();
    }

    public function view(User $user, StockMovement $movement): bool
    {
        if ($user->isAdmin() || $user->isAuditor()) {
            return true;
        }

        if ($user->isBranchManager() || $user->isWarehouseStaff()) {
            return $user->canAccessWarehouse($movement->warehouse);
        }

        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, StockMovement $movement): bool
    {
        return false;
    }

    public function delete(User $user, StockMovement $movement): bool
    {
        return false;
    }

    public function restore(User $user, StockMovement $movement): bool
    {
        return false;
    }

    public function forceDelete(User $user, StockMovement $movement): bool
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

    /**
     * [Phase 4 / Principle A8] Sole source of truth for admin/auditor-only
     * review-surface visibility (cross-warehouse filters). Relocated
     * verbatim from Filament visible() closures. FROZEN except for a
     * genuinely new ability or a demonstrated bug.
     */
    public function viewAdminReview(User $user): bool
    {
        return $user->isAdmin() || $user->isAuditor();
    }
}
