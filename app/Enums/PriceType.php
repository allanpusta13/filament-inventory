<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PriceType: string implements HasColor, HasIcon, HasLabel
{
    case Cost = 'cost';
    case Sale = 'sale';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cost => __('Cost'),
            self::Sale => __('Sale'),
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Cost => 'heroicon-o-currency-dollar',
            self::Sale => 'heroicon-o-tag',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Cost => 'warning',
            self::Sale => 'success',
        };
    }
}
