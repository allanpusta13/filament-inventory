<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\InTransit;
use App\Models\User;

class InTransitPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, InTransit $inTransit): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, InTransit $inTransit): bool
    {
        return false;
    }

    public function delete(User $user, InTransit $inTransit): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, InTransit $inTransit): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, InTransit $inTransit): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function restoreAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function receive(User $user, InTransit $inTransit): bool
    {
        return true;
    }
}