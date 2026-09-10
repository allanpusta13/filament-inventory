<?php

declare(strict_types=1);

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum StockMovementType: string implements HasColor, HasIcon, HasLabel
{
    case Receive = 'receive';
    case Ship = 'ship';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case TransitOut = 'transit_out';
    case TransitIn = 'transit_in';
    case Adjustment = 'adjustment';
    case Loss = 'loss';

    public function getLabel(): string
    {
        return match ($this) {
            self::Receive => 'Receive',
            self::Ship => 'Ship',
            self::TransferOut => 'Transfer out',
            self::TransferIn => 'Transfer in',
            self::TransitOut => 'Transit out',
            self::TransitIn => 'Transit in',
            self::Adjustment => 'Adjustment',
            self::Loss => 'Loss',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Receive, self::TransferIn, self::TransitIn => 'success',
            self::Ship, self::TransferOut, self::TransitOut => 'info',
            self::Adjustment => 'warning',
            self::Loss => 'danger',
        };
    }

    public function getIcon(): string|BackedEnum|null
    {
        return match ($this) {
            self::Receive => Heroicon::ArrowDownTray,
            self::Ship => Heroicon::ArrowUpTray,
            self::TransferOut => Heroicon::ArrowUpOnSquare,
            self::TransferIn => Heroicon::ArrowDownOnSquare,
            self::TransitOut => Heroicon::Truck,
            self::TransitIn => Heroicon::Truck,
            self::Adjustment => Heroicon::AdjustmentsHorizontal,
            self::Loss => Heroicon::ExclamationTriangle,
        };
    }

    public function isInbound(): bool
    {
        return in_array($this, [self::Receive, self::TransferIn, self::TransitIn], true);
    }

    public function isOutbound(): bool
    {
        return in_array($this, [self::Ship, self::TransferOut, self::TransitOut, self::Loss], true);
    }
}
