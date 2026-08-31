<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum InTransitStatus: string implements HasColor, HasLabel
{
    case InTransit = 'in_transit';
    case PartiallyReceived = 'partially_received';
    case Cleared = 'cleared';

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::InTransit => 'info',
            self::PartiallyReceived => 'warning',
            self::Cleared => 'success',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::InTransit => 'In Transit',
            self::PartiallyReceived => 'Partially Received',
            self::Cleared => 'Cleared',
        };
    }
}
