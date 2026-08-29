<?php

declare(strict_types=1);

namespace App\Filament\Traits;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Session;

trait DashboardFilterable
{
    protected function getFilterWarehouseIds(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        if ($user->role === UserRole::Admin->value) {
            $selected = Session::get('admin_warehouse_filter');

            return $selected ? [(int) $selected] : null;
        }

        if ($user->role === UserRole::WarehouseStaff->value) {
            return $user->warehouses->pluck('id')->all();
        }

        return null;
    }
}
