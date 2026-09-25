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

    public function view(User $user, LossLedger $l): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, LossLedger $l): bool
    {
        return false;
    }

    public function delete(User $user, LossLedger $l): bool
    {
        return false;
    }

    public function viewAuditFilters(User $user): bool
    {
        return $user->isAdmin() || $user->isAuditor();
    }
}
