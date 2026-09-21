<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isAuditor() || $user->isWarehouseStaff();
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->isAdmin() || $user->isAuditor() || $user->isWarehouseStaff();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isWarehouseStaff();
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->isAdmin() || $user->isWarehouseStaff();
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Customer $customer): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Customer $customer): bool
    {
        return $user->isAdmin();
    }
}
