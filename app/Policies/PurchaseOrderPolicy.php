<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;

class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PurchaseOrder $o): bool
    {
        return $user->isAdmin() || $user->warehouses->contains($o->warehouse_id);
    }

    public function create(User $user): bool
    {
        return $user->warehouses()->exists();
    }

    public function update(User $user, PurchaseOrder $o): bool
    {
        return $o->status === \App\Enums\PurchaseOrderStatus::Draft
            && $user->warehouses->contains($o->warehouse_id);
    }

    public function delete(User $user, PurchaseOrder $o): bool
    {
        return in_array($o->status, [
            \App\Enums\PurchaseOrderStatus::Draft,
            \App\Enums\PurchaseOrderStatus::Cancelled,
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
        return $user->warehouses->contains($o->warehouse_id);
    }

    public function receivePurchase(User $user, PurchaseOrder $o): bool
    {
        return $user->warehouses->contains($o->warehouse_id);
    }

    public function cancelPurchase(User $user, PurchaseOrder $o): bool
    {
        return $o->canBeCancelled()
            && ($user->isAdmin() || $user->warehouses->contains($o->warehouse_id));
    }

    public function viewAuditFilters(User $user): bool
    {
        return $user->isAdmin() || $user->isAuditor();
    }
}
