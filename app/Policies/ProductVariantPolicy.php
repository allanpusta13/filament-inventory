<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProductVariant;
use App\Models\User;

class ProductVariantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isAuditor() || $user->isBranchManager() || $user->isWarehouseStaff();
    }

    public function view(User $user, ProductVariant $variant): bool
    {
        return $user->isAdmin() || $user->isAuditor() || $user->isBranchManager() || $user->isWarehouseStaff();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isBranchManager() || $user->isWarehouseStaff();
    }

    public function update(User $user, ProductVariant $variant): bool
    {
        return $user->isAdmin() || $user->isBranchManager() || $user->isWarehouseStaff();
    }

    public function delete(User $user, ProductVariant $variant): bool
    {
        return $user->isAdmin() || $user->isBranchManager() || $user->isWarehouseStaff();
    }

    public function restore(User $user, ProductVariant $variant): bool
    {
        return $user->isAdmin() || $user->isAuditor();
    }

    public function forceDelete(User $user, ProductVariant $variant): bool
    {
        return $user->isAdmin();
    }

    public function setPrice(User $user, ProductVariant $variant): bool
    {
        return $user->isAdmin() || $user->isBranchManager() || $user->isWarehouseStaff();
    }

    public function adjustStock(User $user, ProductVariant $variant): bool
    {
        return $user->isAdmin();
    }
}
