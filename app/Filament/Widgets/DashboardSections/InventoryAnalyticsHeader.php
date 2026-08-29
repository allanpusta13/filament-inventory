<?php

declare(strict_types=1);

namespace App\Filament\Widgets\DashboardSections;

final class InventoryAnalyticsHeader extends BaseSectionHeaderWidget
{
    protected static ?int $sort = 30;

    public function __construct()
    {
        $this->title = 'Inventory Analytics';
        $this->description = 'Stock movement trends and category distribution';
        $this->icon = 'trend';
        $this->iconColor = 'indigo';
    }
}
