<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\User;

/**
 * PurchaseOrder policy — §8.7.
 *
 * ⚠ BranchManager: same tier as WarehouseStaff for operational
 * abilities (create, update, order, receive, cancel, delete). Admin-only
 * abilities (restore, forceDelete, deleteAny, viewAuditFilters) remain
 * admin-only.
 */
class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PurchaseOrder $o): bool
    {
        return $user->isAdmin() || $user->isAuditor() || $user->warehouses->contains($o->warehouse_id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || (! $user->isAuditor() && $user->warehouses()->exists());
    }

    public function update(User $user, PurchaseOrder $o): bool
    {
        return ! $user->isAuditor()
            && $o->status === PurchaseOrderStatus::Draft
            && $user->warehouses->contains($o->warehouse_id);
    }

    public function delete(User $user, PurchaseOrder $o): bool
    {
        return ! $user->isAuditor() && in_array($o->status, [
            PurchaseOrderStatus::Draft,
            PurchaseOrderStatus::Cancelled,
        ], true) && $user->warehouses->contains($o->warehouse_id);
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, PurchaseOrder $o): bool
    {
        return $user->isAdmin();
    }

    public function restoreAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, PurchaseOrder $o): bool
    {
        return $user->isAdmin();
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function orderPurchase(User $user, PurchaseOrder $o): bool
    {
        return ! $user->isAuditor() && ($user->isAdmin()
            || $user->warehouses->contains($o->warehouse_id));
    }

    public function receivePurchase(User $user, PurchaseOrder $o): bool
    {
        return ! $user->isAuditor() && ($user->isAdmin()
            || $user->warehouses->contains($o->warehouse_id));
    }

    public function cancelPurchase(User $user, PurchaseOrder $o): bool
    {
        return ! $user->isAuditor() && $o->canBeCancelled()
            && ($user->isAdmin() || $user->warehouses->contains($o->warehouse_id));
    }

    public function viewAuditFilters(User $user): bool
    {
        return $user->isAdmin() || $user->isAuditor();
    }
}
