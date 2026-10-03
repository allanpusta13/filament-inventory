<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\SalesOrderStatus;
use App\Models\SalesOrder;
use App\Models\User;

/**
 * SalesOrder policy — §8.8.
 *
 * ⚠ BranchManager: same tier as WarehouseStaff for operational
 * abilities. Admin-only abilities remain admin-only.
 */
class SalesOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SalesOrder $o): bool
    {
        return $user->isAdmin() || $user->isAuditor() || $user->warehouses->contains($o->warehouse_id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || (! $user->isAuditor() && $user->warehouses()->exists());
    }

    public function update(User $user, SalesOrder $o): bool
    {
        return ! $user->isAuditor()
            && $o->status === SalesOrderStatus::Draft
            && $user->warehouses->contains($o->warehouse_id);
    }

    public function delete(User $user, SalesOrder $o): bool
    {
        return $user->isAdmin() && in_array($o->status, [
            SalesOrderStatus::Draft,
            SalesOrderStatus::Cancelled,
        ], true);
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, SalesOrder $o): bool
    {
        return $user->isAdmin();
    }

    public function restoreAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, SalesOrder $o): bool
    {
        return $user->isAdmin();
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function confirmSalesOrder(User $user, SalesOrder $o): bool
    {
        return ! $user->isAuditor() && ($user->isAdmin()
            || $user->warehouses->contains($o->warehouse_id));
    }

    public function dispatchSale(User $user, SalesOrder $o): bool
    {
        return ! $user->isAuditor() && ($user->isAdmin()
            || $user->warehouses->contains($o->warehouse_id));
    }

    public function recordSalesReturn(User $user, SalesOrder $o): bool
    {
        return ! $user->isAuditor() && ($user->isAdmin()
            || $user->warehouses->contains($o->warehouse_id));
    }

    public function cancelSalesOrder(User $user, SalesOrder $o): bool
    {
        return ! $user->isAuditor() && $o->canBeCancelled()
            && ($user->isAdmin() || $user->warehouses->contains($o->warehouse_id));
    }

    public function viewAuditFilters(User $user): bool
    {
        return $user->isAdmin() || $user->isAuditor();
    }
}
