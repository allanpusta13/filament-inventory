<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Widgets\ActiveInTransitWidget;
use App\Filament\Widgets\LowStockAlertsWidget;
use App\Filament\Widgets\RecentMovementsWidget;
use App\Filament\Widgets\StatsOverview;
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
            UserRole::ADMIN,
            UserRole::BRANCH_MANAGER,
            UserRole::WAREHOUSE_STAFF,
            UserRole::AUDITOR,
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
            StatsOverview::class,
            LowStockAlertsWidget::class,
            RecentMovementsWidget::class,
            ActiveInTransitWidget::class,
        ];
    }
}
