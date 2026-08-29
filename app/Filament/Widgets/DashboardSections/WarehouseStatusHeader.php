<?php

declare(strict_types=1);

namespace App\Filament\Widgets\DashboardSections;

final class WarehouseStatusHeader extends BaseSectionHeaderWidget
{
    protected static ?int $sort = 20;

    public function __construct()
    {
        $this->title = 'Warehouse Status';
        $this->description = 'Inventory summary across all warehouse locations';
        $this->icon = 'warehouse';
        $this->iconColor = 'emerald';
    }

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user?->isAdmin() ?? false;
    }
}
