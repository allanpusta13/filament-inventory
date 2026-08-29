<?php

declare(strict_types=1);

namespace App\Filament\Widgets\DashboardSections;

use Filament\Widgets\Widget;

abstract class BaseSectionHeaderWidget extends Widget
{
    public string $title = '';

    public ?string $description = null;

    public string $icon = 'chart';

    public string $iconColor = 'primary';

    protected string $view = 'filament.dashboard-sections.section-header';

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'icon' => $this->icon,
            'iconColor' => $this->iconColor,
        ];
    }
}
