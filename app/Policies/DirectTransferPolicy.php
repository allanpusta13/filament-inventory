<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DirectTransfer;
use App\Models\User;

class DirectTransferPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, DirectTransfer $t): bool
    {
        return $user->isAdmin()
            || $user->isAuditor()
            || ($user->warehouses->contains($t->from_warehouse_id)
                && $user->warehouses->contains($t->to_warehouse_id));
    }

    public function create(User $user): bool
    {
        return $user->warehouses()->count() >= 1;
    }

    public function update(User $user, DirectTransfer $t): bool
    {
        return false; // fire-and-forget: no edit
    }

    public function delete(User $user, DirectTransfer $t): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function viewAuditFilters(User $user): bool
    {
        return $user->isAdmin() || $user->isAuditor();
    }
}
