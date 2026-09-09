<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Widgets\BentoStatsWidget;
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
            // BentoStatsWidget::class,
        ];
    }

    public function getWidgets(): array
    {
        return [
            // BentoStatsWidget::class,
        ];
    }
}
