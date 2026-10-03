<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Purchase order lifecycle status.
 *
 * Blueprint §4.2 — implements HasLabel + HasColor.
 *
 * Extension (authorized per standing instruction): also implements
 * HasIcon so the enum's rendered state carries a semantic Heroicon
 * alongside its translated label and color. Icons are code-level
 * presentation metadata (untranslated), mirroring the existing color
 * contract (§0A.1 item 19).
 *
 * Lifecycle (§0 core principle 5 / A2):
 *   draft → ordered → partially_received → received / cancelled
 */
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

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Ordered => 'primary',
            self::PartiallyReceived => 'warning',
            self::Received => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Draft => Heroicon::OutlinedPencilSquare,
            self::Ordered => Heroicon::OutlinedPaperAirplane,
            self::PartiallyReceived => Heroicon::OutlinedArchiveBoxArrowDown,
            self::Received => Heroicon::OutlinedCheckCircle,
            self::Cancelled => Heroicon::OutlinedXMark,
        };
    }
}
