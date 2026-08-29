<?php

declare(strict_types=1);

namespace App\Filament\Widgets\DashboardSections;

final class PerformanceOverviewHeader extends BaseSectionHeaderWidget
{
    protected static ?int $sort = 0;

    public function __construct()
    {
        $this->title = 'Performance Overview';
        $this->description = 'Key inventory metrics at a glance';
        $this->icon = 'chart';
        $this->iconColor = 'primary';
    }
}
