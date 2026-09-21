<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isAuditor() || $user->isWarehouseStaff();
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->isAdmin() || $user->isAuditor() || $user->isWarehouseStaff();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isWarehouseStaff();
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->isAdmin() || $user->isWarehouseStaff();
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Supplier $supplier): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Supplier $supplier): bool
    {
        return $user->isAdmin();
    }
}
