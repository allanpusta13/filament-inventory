<?php

declare(strict_types=1);

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum NegotiationSide: string implements HasColor, HasIcon, HasLabel
{
    case Fulfiller = 'fulfiller';
    case Requestor = 'requestor';

    public function getLabel(): string
    {
        return match ($this) {
            self::Fulfiller => __('Fulfiller'),
            self::Requestor => __('Requestor'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Fulfiller => 'primary',
            self::Requestor => 'info',
        };
    }

    public function getIcon(): string|BackedEnum|null
    {
        return match ($this) {
            self::Fulfiller => Heroicon::BuildingStorefront,
            self::Requestor => Heroicon::HandRaised,
        };
    }

    public function opposite(): self
    {
        return match ($this) {
            self::Fulfiller => self::Requestor,
            self::Requestor => self::Fulfiller,
        };
    }
}
