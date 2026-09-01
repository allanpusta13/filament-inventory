<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class ProductPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        // All authenticated roles can view products
        return true;
    }

    public function view(User $user, Product $product): bool
    {
        // All authenticated roles can view products
        return true;
    }

    public function create(User $user): bool
    {
        // Only admin can create products
        return $user->isAdmin();
    }

    public function update(User $user, Product $product): bool
    {
        // Only admin can update products
        return $user->isAdmin();
    }

    public function delete(User $user, Product $product): bool
    {
        // Only admin can delete products
        return $user->isAdmin();
    }
}
