<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LossLedger;
use App\Models\User;

class LossLedgerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isAuditor() || $user->isBranchManager() || $user->isWarehouseStaff();
    }

    public function view(User $user, LossLedger $lossLedger): bool
    {
        if ($user->isAdmin() || $user->isAuditor()) {
            return true;
        }

        if ($user->isBranchManager() || $user->isWarehouseStaff()) {
            return $user->canAccessWarehouse($lossLedger->warehouse);
        }

        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, LossLedger $lossLedger): bool
    {
        return false;
    }

    public function delete(User $user, LossLedger $lossLedger): bool
    {
        return false;
    }

    public function restore(User $user, LossLedger $lossLedger): bool
    {
        return false;
    }

    public function forceDelete(User $user, LossLedger $lossLedger): bool
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

    public function recordLoss(User $user): bool
    {
        return $user->isAdmin() || $user->isBranchManager() || $user->isWarehouseStaff();
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
