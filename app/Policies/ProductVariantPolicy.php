<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProductVariant;
use App\Models\User;

class ProductVariantPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ProductVariant $variant): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ProductVariant $variant): bool
    {
        return true;
    }

    public function delete(User $user, ProductVariant $variant): bool
    {
        return true;
    }

    public function restore(User $user, ProductVariant $variant): bool
    {
        return true;
    }

    public function forceDelete(User $user, ProductVariant $variant): bool
    {
        return false;
    }

    public function setPrice(User $user, ProductVariant $variant): bool
    {
        return true;
    }

    public function adjustStock(User $user, ProductVariant $variant): bool
    {
        return true;
    }
}
