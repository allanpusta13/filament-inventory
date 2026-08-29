<?php

declare(strict_types=1);

namespace App\Traits;

use App\Filament\Widgets\WarehouseFilterWidget;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait DashboardFilterable
{
    /**
     * Get warehouse IDs to filter by based on user role and dashboard filter.
     *
     * @return list<int>|null null means no filter (show all)
     */
    protected function getFilterWarehouseIds(User $user): ?array
    {
        $sessionFilter = WarehouseFilterWidget::getSelectedWarehouseId();

        if ($sessionFilter !== null) {
            return [$sessionFilter];
        }

        if (! $user->isAdmin()) {
            return $user->warehouses()->pluck('warehouses.id')->toArray();
        }

        return null;
    }

    /**
     * Apply warehouse scope to a query builder.
     *
     * @param  Builder<*>  $query
     * @return Builder<*>
     */
    protected function scopeToWarehouses(Builder $query, User $user, string $column = 'warehouse_id'): Builder
    {
        $ids = $this->getFilterWarehouseIds($user);

        if ($ids !== null) {
            $query->whereIn($column, $ids);
        }

        return $query;
    }
}
