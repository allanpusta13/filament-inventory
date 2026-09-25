<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SalesOrder;
use App\Models\User;

class SalesOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SalesOrder $o): bool
    {
        return $user->isAdmin() || $user->warehouses->contains($o->warehouse_id);
    }

    public function create(User $user): bool
    {
        return $user->warehouses()->exists();
    }

    public function update(User $user, SalesOrder $o): bool
    {
        return $o->status === \App\Enums\SalesOrderStatus::Draft
            && $user->warehouses->contains($o->warehouse_id);
    }

    public function delete(User $user, SalesOrder $o): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function confirmSalesOrder(User $user, SalesOrder $o): bool
    {
        return $user->warehouses->contains($o->warehouse_id);
    }

    public function dispatchSale(User $user, SalesOrder $o): bool
    {
        return $user->warehouses->contains($o->warehouse_id);
    }

    public function recordSalesReturn(User $user, SalesOrder $o): bool
    {
        return $user->warehouses->contains($o->warehouse_id);
    }

    public function cancelSalesOrder(User $user, SalesOrder $o): bool
    {
        return $user->isAdmin() || $user->warehouses->contains($o->warehouse_id);
    }

    public function viewAuditFilters(User $user): bool
    {
        return $user->isAdmin() || $user->isAuditor();
    }
}
