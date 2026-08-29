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
    public function getColumns(): int|array
    {
        return [
            'default' => 1,   // < 640px (mobile)
            'sm' => 1,        // 640px+ (small tablets)
            'md' => 2,        // 768px+ (tablets)
            'lg' => 12,       // 1024px+ (desktops)
            'xl' => 12,       // 1280px+ (large desktops)
        ];
    }

    public function getWidgets(): array
    {
        return [
            StatsOverviewWidget::class,       // Row 1: KPI overview (span 12)
            LowStockAlertWidget::class,       // Row 2-left: Low Stock Alerts (span 7)
            QuickActionsWidget::class,        // Row 2-right: Common Actions (span 5)
            StockByWarehouseWidget::class,    // Row 3: Warehouse Summary (span 12)
            StockMovementTrendChart::class,   // Row 4-left: Inflow vs Outflow (span 7)
            CategoryStockChart::class,        // Row 4-right: By Category (span 5)
            FastMovingStockChart::class,      // Row 5: High-Turnover Products (span 12)
            RecentStockActivityWidget::class, // Row 6: Recent Movements (span 12)
            AccountWidget::class,             // Row 7: Account (final)
        ];
    }
}
