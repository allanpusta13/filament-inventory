<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Widgets\BentoStatsWidget;
use App\Filament\Widgets\LowStockAlertsWidget;
use App\Filament\Widgets\PendingRequisitionsWidget;
use App\Filament\Widgets\RecentMovementsChartWidget;
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
            // We are removing the WarehouseFilterWidget for now to simplify the header
            // If needed, we can add it back later, but the spec doesn't mention it.
        ];
    }

    public function getWidgets(): array
    {
        return [
            BentoStatsWidget::class,
            RecentMovementsChartWidget::class,
            LowStockAlertsWidget::class,
            PendingRequisitionsWidget::class,
        ];
    }
}