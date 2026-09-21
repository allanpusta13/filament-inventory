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
        return $user->isAdmin() || $user->isAuditor() || $user->isWarehouseStaff();
    }

    public function view(User $user, SalesOrder $salesOrder): bool
    {
        return $user->isAdmin() || $user->isAuditor() || $user->isWarehouseStaff();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isWarehouseStaff();
    }

    public function update(User $user, SalesOrder $salesOrder): bool
    {
        return $user->isAdmin() || ($user->isWarehouseStaff() && $salesOrder->status === SalesOrderStatus::Draft);
    }

    public function delete(User $user, SalesOrder $salesOrder): bool
    {
        return $user->isAdmin() || ($user->isWarehouseStaff() && in_array($salesOrder->status, [
            SalesOrderStatus::Draft,
            SalesOrderStatus::Cancelled,
        ]));
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
        return $user->isAdmin() || ($user->isWarehouseStaff() && $salesOrder->status === SalesOrderStatus::Draft);
    }

    public function dispatchSale(User $user, SalesOrder $salesOrder): bool
    {
        return $user->isAdmin() || ($user->isWarehouseStaff() && in_array($salesOrder->status, [
            SalesOrderStatus::Confirmed,
            SalesOrderStatus::PartiallyDispatched,
        ]));
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
