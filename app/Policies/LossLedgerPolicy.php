<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\LossLedger;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class LossLedgerPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        // All roles can view at least some loss ledger entries (see view method for restrictions)
        return true;
    }

    public function view(User $user, LossLedger $lossLedger): bool
    {
        if ($user->role === UserRole::Admin || $user->role === UserRole::Auditor) {
            return true;
        }

        if ($user->role === UserRole::BranchManager || $user->role === UserRole::WarehouseStaff) {
            // Restrict to losses occurring at their assigned warehouse IDs
            $assignedWarehouseIds = $user->warehouses()->pluck('id')->toArray();

            return in_array($lossLedger->warehouse_id, $assignedWarehouseIds);
        }

        return false;
    }

    public function create(User $user): bool
    {
        // Admin and branch managers can create loss ledger entries via UI
        return $user->role === UserRole::Admin || $user->role === UserRole::BranchManager;
    }

    public function update(User $user, LossLedger $lossLedger): bool
    {
        // Only admin can update via UI
        return $user->role === UserRole::Admin;
    }

    public function delete(User $user, LossLedger $lossLedger): bool
    {
        // Only admin can delete via UI
        return $user->role === UserRole::Admin;
    }
}
