<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class StockMovementPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        // All roles can view at least some stock movements (see view method for restrictions)
        return true;
    }

    public function view(User $user, StockMovement $movement): bool
    {
        if ($user->role === UserRole::Admin || $user->role === UserRole::Auditor) {
            return true;
        }

        if ($user->role === UserRole::BranchManager || $user->role === UserRole::WarehouseStaff) {
            // Restrict to movements occurring at their assigned warehouse IDs
            $assignedWarehouseIds = $user->warehouses()->pluck('id')->toArray();

            return in_array($movement->warehouse_id, $assignedWarehouseIds);
        }

        return false;
    }

    public function create(User $user): bool
    {
        // Admin, staff, and managers can create stock movements via UI
        return $user->role === UserRole::Admin ||
               $user->role === UserRole::BranchManager ||
               $user->role === UserRole::WarehouseStaff;
    }

    public function update(User $user, StockMovement $movement): bool
    {
        // Only admin can update via UI (emergency overrides with audit note)
        return $user->role === UserRole::Admin;
    }

    public function delete(User $user, StockMovement $movement): bool
    {
        // Only admin can delete via UI (emergency overrides with audit note)
        return $user->role === UserRole::Admin;
    }
}
