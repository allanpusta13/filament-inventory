<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StockMovement;
use App\Models\User;

class StockMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, StockMovement $m): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, StockMovement $m): bool
    {
        return false;
    }

    public function delete(User $user, StockMovement $m): bool
    {
        return false;
    }

    public function viewAuditFilters(User $user): bool
    {
        return $user->isAdmin() || $user->isAuditor();
    }

    // NOTE: createDirectTransfer() removed — see DirectTransferPolicy::create().
}
