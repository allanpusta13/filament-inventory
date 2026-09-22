<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isAuditor() || $user->isBranchManager() || $user->isWarehouseStaff();
    }

    public function view(User $user, Product $product): bool
    {
        return $user->isAdmin() || $user->isAuditor() || $user->isBranchManager() || $user->isWarehouseStaff();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isBranchManager() || $user->isWarehouseStaff();
    }

    public function update(User $user, Product $product): bool
    {
        return $user->isAdmin() || $user->isBranchManager() || $user->isWarehouseStaff();
    }

    public function delete(User $user, Product $product): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $product->variants()->whereNull('deleted_at')->count() === 0;
    }

    public function restore(User $user, Product $product): bool
    {
        return $user->isAdmin() || $user->isAuditor();
    }

    public function forceDelete(User $user, Product $product): bool
    {
        return $user->isAdmin();
    }
}