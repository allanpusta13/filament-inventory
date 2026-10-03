<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Widgets\ActiveInTransitWidget;
use App\Filament\Widgets\LowStockAlertsWidget;
use App\Filament\Widgets\RecentMovementsWidget;
use App\Filament\Widgets\StatsOverviewWidget;
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
            StatsOverviewWidget::class,
            LowStockAlertsWidget::class,
            RecentMovementsWidget::class,
            ActiveInTransitWidget::class,
        ];
    }
}
