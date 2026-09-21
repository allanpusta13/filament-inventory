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
        return $user->isAdmin() || $user->isAuditor() || $user->isWarehouseStaff();
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->isAdmin() || $user->isAuditor() || $user->isWarehouseStaff();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isWarehouseStaff();
    }

    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->isAdmin() || ($user->isWarehouseStaff() && $purchaseOrder->status === PurchaseOrderStatus::Draft);
    }

    public function delete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->isAdmin() || ($user->isWarehouseStaff() && in_array($purchaseOrder->status, [
            PurchaseOrderStatus::Draft,
            PurchaseOrderStatus::Cancelled,
        ]));
    }

    public function restore(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->isAdmin();
    }

    public function orderPurchase(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->isAdmin() || ($user->isWarehouseStaff() && $purchaseOrder->status === PurchaseOrderStatus::Draft);
    }

    public function receivePurchase(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->isAdmin() || ($user->isWarehouseStaff() && in_array($purchaseOrder->status, [
            PurchaseOrderStatus::Ordered,
            PurchaseOrderStatus::PartiallyReceived,
        ]));
    }

    public function cancelPurchase(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->isAdmin() || ($user->isWarehouseStaff() && in_array($purchaseOrder->status, [
            PurchaseOrderStatus::Draft,
            PurchaseOrderStatus::Ordered,
        ]) && $purchaseOrder->items->every(fn ($item) => $item->received_base_qty === 0));
    }
}
