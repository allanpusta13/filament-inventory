<?php

declare(strict_types=1);

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum PurchaseOrderStatus: string implements HasColor, HasIcon, HasLabel
{
    case Draft = 'draft';
    case Ordered = 'ordered';
    case PartiallyReceived = 'partially_received';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Ordered => __('Ordered'),
            self::PartiallyReceived => __('Partially received'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Ordered => 'info',
            self::PartiallyReceived => 'warning',
            self::Completed => 'success',
            self::Cancelled => 'gray',
        };
    }

    public function getIcon(): string|BackedEnum|null
    {
        return match ($this) {
            self::Draft => Heroicon::DocumentText,
            self::Ordered => Heroicon::PaperAirplane,
            self::PartiallyReceived => Heroicon::ArchiveBoxArrowDown,
            self::Completed => Heroicon::CheckBadge,
            self::Cancelled => Heroicon::XCircle,
        };
    }
}
