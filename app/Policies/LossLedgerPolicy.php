<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LossLedger;
use App\Models\User;

class LossLedgerPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, LossLedger $lossLedger): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, LossLedger $lossLedger): bool
    {
        return false;
    }

    public function delete(User $user, LossLedger $lossLedger): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, LossLedger $lossLedger): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, LossLedger $lossLedger): bool
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

    public function recordLoss(User $user): bool
    {
        return true;
    }
}
