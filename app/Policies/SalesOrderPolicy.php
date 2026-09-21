<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\SalesOrderStatus;
use App\Models\SalesOrder;
use App\Models\User;

class SalesOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isAuditor() || $user->isBranchManager() || $user->isWarehouseStaff();
    }

    public function view(User $user, SalesOrder $salesOrder): bool
    {
        if ($user->isAdmin() || $user->isAuditor()) {
            return true;
        }

        if ($user->isBranchManager() || $user->isWarehouseStaff()) {
            return $user->canAccessWarehouse($salesOrder->warehouse);
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isBranchManager() || $user->isWarehouseStaff();
    }

    public function update(User $user, SalesOrder $salesOrder): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isBranchManager() || $user->isWarehouseStaff()) {
            return $user->canAccessWarehouse($salesOrder->warehouse)
                && $salesOrder->status === SalesOrderStatus::Draft;
        }

        return false;
    }

    public function delete(User $user, SalesOrder $salesOrder): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isBranchManager() || $user->isWarehouseStaff()) {
            return $user->canAccessWarehouse($salesOrder->warehouse)
                && in_array($salesOrder->status, [
                    SalesOrderStatus::Draft,
                    SalesOrderStatus::Cancelled,
                ]);
        }

        return false;
    }

    public function restore(User $user, SalesOrder $salesOrder): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, SalesOrder $salesOrder): bool
    {
        return $user->isAdmin();
    }

    public function confirmSalesOrder(User $user, SalesOrder $salesOrder): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isBranchManager() || $user->isWarehouseStaff()) {
            return $user->canAccessWarehouse($salesOrder->warehouse)
                && $salesOrder->status === SalesOrderStatus::Draft;
        }

        return false;
    }

    public function dispatchSale(User $user, SalesOrder $salesOrder): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isBranchManager() || $user->isWarehouseStaff()) {
            return $user->canAccessWarehouse($salesOrder->warehouse)
                && in_array($salesOrder->status, [
                    SalesOrderStatus::Confirmed,
                    SalesOrderStatus::PartiallyDispatched,
                ]);
        }

        return false;
    }

    public function recordSalesReturn(User $user, SalesOrder $salesOrder): bool
    {
        return $user->isAdmin() || ($user->isWarehouseStaff() && $salesOrder->items->contains(fn ($item) => $item->dispatched_base_qty > 0));
    }

    public function cancelSalesOrder(User $user, SalesOrder $salesOrder): bool
    {
        return $user->isAdmin() || ($user->isWarehouseStaff() && in_array($salesOrder->status, [
            SalesOrderStatus::Draft,
            SalesOrderStatus::Confirmed,
        ]));
    }
}
