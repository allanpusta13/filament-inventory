<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Sales order lifecycle status.
 *
 * Blueprint §4.3 — implements HasLabel + HasColor.
 *
 * Extension (authorized per standing instruction): also implements
 * HasIcon so the enum's rendered state carries a semantic Heroicon
 * alongside its translated label and color. Icons are code-level
 * presentation metadata (untranslated), mirroring the existing color
 * contract (§0A.1 item 19).
 *
 * Lifecycle (§0 core principle 5 / A2):
 *   draft → confirmed → partially_dispatched → dispatched / cancelled
 */
enum SalesOrderStatus: string implements HasColor, HasIcon, HasLabel
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case PartiallyDispatched = 'partially_dispatched';
    case Dispatched = 'dispatched';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => __('enums.sales_order_status.draft'),
            self::Confirmed => __('enums.sales_order_status.confirmed'),
            self::PartiallyDispatched => __('enums.sales_order_status.partially_dispatched'),
            self::Dispatched => __('enums.sales_order_status.dispatched'),
            self::Cancelled => __('enums.sales_order_status.cancelled'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Confirmed => 'primary',
            self::PartiallyDispatched => 'warning',
            self::Dispatched => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Draft => Heroicon::OutlinedPencilSquare,
            self::Confirmed => Heroicon::OutlinedCheckCircle,
            self::PartiallyDispatched => Heroicon::OutlinedTruck,
            self::Dispatched => Heroicon::OutlinedCheckBadge,
            self::Cancelled => Heroicon::OutlinedXMark,
        };
    }
}
