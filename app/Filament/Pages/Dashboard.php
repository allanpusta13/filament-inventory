<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\LowStockAlertWidget;
use App\Filament\Widgets\RecentStockActivityWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use App\Filament\Widgets\StockByWarehouseWidget;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\AccountWidget;

final class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            StatsOverviewWidget::class,
            LowStockAlertWidget::class,
            RecentStockActivityWidget::class,
            StockByWarehouseWidget::class,
            AccountWidget::class,
        ];
    }
}
