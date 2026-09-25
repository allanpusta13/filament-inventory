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

    public function view(User $user, InTransit $t): bool
    {
        return true;
    }
}
