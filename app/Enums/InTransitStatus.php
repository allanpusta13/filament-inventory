<?php

declare(strict_types=1);

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum InTransitStatus: string implements HasColor, HasIcon, HasLabel
{
    case InTransit = 'in_transit';
    case Cleared = 'cleared';
    case Lost = 'lost';

    public function getLabel(): string
    {
        return match ($this) {
            self::InTransit => __('enums.in_transit_status.in_transit'),
            self::Cleared => __('enums.in_transit_status.cleared'),
            self::Lost => __('enums.in_transit_status.lost'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::InTransit => 'info',
            self::Lost => 'warning',
            self::Cleared => 'success',
        };
    }

    public function getIcon(): string|BackedEnum|null
    {
        return match ($this) {
            self::InTransit => Heroicon::Truck,
            self::Lost => Heroicon::ArchiveBoxArrowDown,
            self::Cleared => Heroicon::CheckCircle,
        };
    }
}
