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
    case PartiallyReceived = 'partially_received';
    case Cleared = 'cleared';

    public function getLabel(): string
    {
        return match ($this) {
            self::InTransit => 'In transit',
            self::PartiallyReceived => 'Partially received',
            self::Cleared => 'Cleared',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::InTransit => 'info',
            self::PartiallyReceived => 'warning',
            self::Cleared => 'success',
        };
    }

    public function getIcon(): string|BackedEnum|null
    {
        return match ($this) {
            self::InTransit => Heroicon::Truck,
            self::PartiallyReceived => Heroicon::ArchiveBoxArrowDown,
            self::Cleared => Heroicon::CheckCircle,
        };
    }
}
