<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Widgets\CategoryStockChart;
use App\Filament\Widgets\DashboardSections\InventoryAnalyticsHeader;
use App\Filament\Widgets\DashboardSections\OperationsAlertsHeader;
use App\Filament\Widgets\DashboardSections\PerformanceOverviewHeader;
use App\Filament\Widgets\DashboardSections\PlanningActivityHeader;
use App\Filament\Widgets\DashboardSections\WarehouseStatusHeader;
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
    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return in_array($user->role, [
            UserRole::Admin,
            UserRole::BranchManager,
            UserRole::WarehouseStaff,
            UserRole::Auditor,
        ], true);
    }

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
            QuickActionsWidget::class,        // sort: 0
            StatsOverviewWidget::class,       // sort: 1
            PerformanceOverviewHeader::class, // sort: 1
            LowStockAlertWidget::class,       // sort: 10
            OperationsAlertsHeader::class,    // sort: 10
            StockByWarehouseWidget::class,    // sort: 15
            WarehouseStatusHeader::class,     // sort: 15
            StockMovementTrendChart::class,  // sort: 20
            CategoryStockChart::class,       // sort: 21
            InventoryAnalyticsHeader::class, // sort: 21
            FastMovingStockChart::class,      // sort: 25
            RecentStockActivityWidget::class, // sort: 30
            PlanningActivityHeader::class,    // sort: 30
            AccountWidget::class,             // sort: 100
        ];
    }
}
