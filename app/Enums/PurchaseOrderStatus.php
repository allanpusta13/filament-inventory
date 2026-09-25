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
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => __('enums.purchase_order_status.draft'),
            self::Ordered => __('enums.purchase_order_status.ordered'),
            self::PartiallyReceived => __('enums.purchase_order_status.partially_received'),
            self::Received => __('enums.purchase_order_status.received'),
            self::Cancelled => __('enums.purchase_order_status.cancelled'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Ordered => 'primary',
            self::PartiallyReceived => 'warning',
            self::Received => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function getIcon(): string|BackedEnum|null
    {
        return match ($this) {
            self::Draft => Heroicon::DocumentText,
            self::Ordered => Heroicon::PaperAirplane,
            self::PartiallyReceived => Heroicon::ArchiveBoxArrowDown,
            self::Received => Heroicon::CheckBadge,
            self::Cancelled => Heroicon::XCircle,
        };
    }
}
