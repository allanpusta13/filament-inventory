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
    case Purchase = 'purchase';
    case Sale = 'sale';
    case SaleReturn = 'sale_return';
    case PurchaseReturn = 'purchase_return';

    public function getLabel(): string
    {
        return match ($this) {
            self::Receive => __('Receive'),
            self::Ship => __('Ship'),
            self::TransferOut => __('Transfer out'),
            self::TransferIn => __('Transfer in'),
            self::TransitOut => __('Transit out'),
            self::TransitIn => __('Transit in'),
            self::Adjustment => __('Adjustment'),
            self::Loss => __('Loss'),
            self::Purchase => __('Purchase'),
            self::Sale => __('Sale'),
            self::SaleReturn => __('Sale return'),
            self::PurchaseReturn => __('Purchase return'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Receive, self::TransferIn, self::TransitIn, self::Purchase, self::SaleReturn => 'success',
            self::Ship, self::TransferOut, self::TransitOut, self::Sale => 'danger',
            self::Adjustment => 'warning',
            self::Loss => 'danger',
            self::PurchaseReturn => 'warning',
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
            self::Purchase => Heroicon::ShoppingCart,
            self::Sale => Heroicon::Truck,
            self::SaleReturn => Heroicon::ArrowUturnLeft,
            self::PurchaseReturn => Heroicon::ArrowUturnLeft,
        };
    }

    public function isInbound(): bool
    {
        return in_array($this, [
            self::Receive,
            self::TransferIn,
            self::TransitIn,
            self::Purchase,
            self::SaleReturn,
        ], true);
    }

    public function isOutbound(): bool
    {
        return in_array($this, [
            self::Ship,
            self::TransferOut,
            self::TransitOut,
            self::Sale,
            self::Loss,
        ], true);
    }
}
