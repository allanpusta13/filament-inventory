<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\CategoryStockChart;
use App\Filament\Widgets\FastMovingStockChart;
use App\Filament\Widgets\LowStockAlertWidget;
use App\Filament\Widgets\QuickActionsWidget;
use App\Filament\Widgets\RecentStockActivityWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use App\Filament\Widgets\StockByWarehouseWidget;
use App\Filament\Widgets\StockMovementTrendChart;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\AccountWidget;

final class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            QuickActionsWidget::class,
            StatsOverviewWidget::class,
            StockMovementTrendChart::class,
            CategoryStockChart::class,
            FastMovingStockChart::class,
            LowStockAlertWidget::class,
            RecentStockActivityWidget::class,
            StockByWarehouseWidget::class,
            AccountWidget::class,
        ];
    }
}
