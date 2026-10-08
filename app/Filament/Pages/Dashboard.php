<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Widgets;
use Filament\Pages\Dashboard as BaseDashboard;

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
            'lg' => 2,
            'xl' => 2,
        ];
    }

    public function getHeaderWidgets(): array
    {
        return [
            //
        ];
    }

    public function getWidgets(): array
    {
        return [
            Widgets\StatsOverviewWidget::class,
            Widgets\LowStockAlertsWidget::class,
            Widgets\RecentMovementsWidget::class,
            Widgets\ActiveInTransitWidget::class,
            Widgets\PendingFulfillmentWidget::class,
            Widgets\QuickActionsWidget::class,
            Widgets\SalesRevenueTrendWidget::class,
            Widgets\SalesVsPurchasesWidget::class,
            Widgets\TopSellingVariantsWidget::class,
        ];
    }
}
