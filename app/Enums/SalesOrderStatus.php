<?php

declare(strict_types=1);

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum SalesOrderStatus: string implements HasColor, HasIcon, HasLabel
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case PartiallyDispatched = 'partially_dispatched';
    case Dispatched = 'dispatched';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Confirmed => __('Confirmed'),
            self::PartiallyDispatched => __('Partially dispatched'),
            self::Dispatched => __('Dispatched'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Confirmed => 'primary',
            self::PartiallyDispatched => 'warning',
            self::Dispatched => 'info',
            self::Completed => 'success',
            self::Cancelled => 'gray',
        };
    }

    public function getIcon(): string|BackedEnum|null
    {
        return match ($this) {
            self::Draft => Heroicon::DocumentText,
            self::Confirmed => Heroicon::CheckCircle,
            self::PartiallyDispatched => Heroicon::ArchiveBoxArrowDown,
            self::Dispatched => Heroicon::Truck,
            self::Completed => Heroicon::CheckBadge,
            self::Cancelled => Heroicon::XCircle,
        };
    }
}
