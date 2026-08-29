<?php

declare(strict_types=1);

namespace App\Filament\Widgets\DashboardSections;

final class PlanningActivityHeader extends BaseSectionHeaderWidget
{
    protected static ?int $sort = 40;

    public function __construct()
    {
        $this->title = 'Planning & Activity';
        $this->description = 'High-turnover products and recent stock movements';
        $this->icon = 'activity';
        $this->iconColor = 'violet';
    }
}
