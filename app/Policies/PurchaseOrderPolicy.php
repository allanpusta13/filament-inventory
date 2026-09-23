<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\User;

class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isAuditor() || $user->isBranchManager() || $user->isWarehouseStaff();
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        if ($user->isAdmin() || $user->isAuditor()) {
            return true;
        }

        if ($user->isBranchManager() || $user->isWarehouseStaff()) {
            return $user->canAccessWarehouse($purchaseOrder->warehouse);
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isBranchManager() || $user->isWarehouseStaff();
    }

    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isBranchManager() || $user->isWarehouseStaff()) {
            return $user->canAccessWarehouse($purchaseOrder->warehouse)
                && $purchaseOrder->status === PurchaseOrderStatus::Draft;
        }

        return false;
    }

    public function delete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isBranchManager() || $user->isWarehouseStaff()) {
            return $user->canAccessWarehouse($purchaseOrder->warehouse)
                && in_array($purchaseOrder->status, [
                    PurchaseOrderStatus::Draft,
                    PurchaseOrderStatus::Cancelled,
                ]);
        }

        return false;
    }

    public function restore(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->isAdmin();
    }

    public function order(User $user, PurchaseOrder $purchaseOrder): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isBranchManager() || $user->isWarehouseStaff()) {
            return $user->canAccessWarehouse($purchaseOrder->warehouse)
                && $purchaseOrder->status === PurchaseOrderStatus::Draft;
        }

        return false;
    }

    public function receive(User $user, PurchaseOrder $purchaseOrder): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isBranchManager() || $user->isWarehouseStaff()) {
            return $user->canAccessWarehouse($purchaseOrder->warehouse)
                && in_array($purchaseOrder->status, [
                    PurchaseOrderStatus::Ordered,
                    PurchaseOrderStatus::PartiallyReceived,
                ]);
        }

        return false;
    }

    public function cancel(User $user, PurchaseOrder $purchaseOrder): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isBranchManager() || $user->isWarehouseStaff()) {
            return $user->canAccessWarehouse($purchaseOrder->warehouse)
                && in_array($purchaseOrder->status, [
                    PurchaseOrderStatus::Draft,
                    PurchaseOrderStatus::Ordered,
                ]);
        }

        return false;
    }
}
