<?php

declare(strict_types=1);

namespace App\Filament\Widgets\DashboardSections;

final class OperationsAlertsHeader extends BaseSectionHeaderWidget
{
    protected static ?int $sort = 10;

    public function __construct()
    {
        $this->title = 'Operations & Alerts';
        $this->description = 'Immediate action items and quick operations';
        $this->icon = 'alert';
        $this->iconColor = 'warning';
    }
}
