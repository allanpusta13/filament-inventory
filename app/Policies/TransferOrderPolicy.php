<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\TransferOrder;
use App\Models\User;

final class TransferOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TransferOrder $order): bool
    {
        if ($user->role === UserRole::Admin || $user->role === UserRole::Auditor) {
            return true;
        }

        return $user->canAccessWarehouse($order->sender)
            || $user->canAccessWarehouse($order->receiver);
    }

    public function create(User $user): bool
    {
        return $user->role !== UserRole::Auditor;
    }

    public function update(User $user, TransferOrder $order): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        if ($user->role === UserRole::Auditor) {
            return false;
        }

        return $user->canAccessWarehouse($order->sender)
            || $user->canAccessWarehouse($order->receiver);
    }

    public function delete(User $user, TransferOrder $order): bool
    {
        if ($user->role === UserRole::Auditor) {
            return false;
        }

        return $order->status->value === 'draft' && $order->dispatchedBy === null;
    }
}
