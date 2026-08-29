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
use App\Filament\Widgets\WarehouseFilterWidget;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\AccountWidget;

final class Dashboard extends BaseDashboard
{
    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'sm' => 1,
            'md' => 2,
            'lg' => 12,
            'xl' => 12,
        ];
    }

    public function getHeaderWidgets(): array
    {
        return [
            WarehouseFilterWidget::class,
        ];
    }

    public function getWidgets(): array
    {
        return [
            StatsOverviewWidget::class,
            LowStockAlertWidget::class,
            QuickActionsWidget::class,
            StockByWarehouseWidget::class,
            StockMovementTrendChart::class,
            CategoryStockChart::class,
            FastMovingStockChart::class,
            RecentStockActivityWidget::class,
            AccountWidget::class,
        ];
    }
}
