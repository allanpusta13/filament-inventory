<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasIcon;

enum InTransitStatus: string implements HasColor, HasLabel, HasIcon
{
    case InTransit = 'in_transit';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Cleared = 'cleared';

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::InTransit => 'info',
            self::PartiallyReceived => 'warning',
            self::Received => 'success',
            self::Cleared => 'success',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::InTransit => 'In Transit',
            self::PartiallyReceived => 'Partially Received',
            self::Received => 'Received',
            self::Cleared => 'Cleared',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::InTransit => 'heroicon-o-truck',
            self::PartiallyReceived => 'heroicon-o-chart-bar',
            self::Received => 'heroicon-o-check-circle',
            self::Cleared => 'heroicon-o-check-circle',
        };
    }
}
