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
    case Adjustment = 'adjustment';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case Loss = 'loss';
    case Damage = 'damage';
    case Purchase = 'purchase';
    case Sale = 'sale';
    case SaleReturn = 'sale_return';
    case PurchaseReturn = 'purchase_return';

    public function getLabel(): string
    {
        return match ($this) {
            self::Adjustment => __('enums.stock_movement_type.adjustment'),
            self::TransferOut => __('enums.stock_movement_type.transfer_out'),
            self::TransferIn => __('enums.stock_movement_type.transfer_in'),
            self::Loss => __('enums.stock_movement_type.loss'),
            self::Damage => __('enums.stock_movement_type.damage'),
            self::Purchase => __('enums.stock_movement_type.purchase'),
            self::Sale => __('enums.stock_movement_type.sale'),
            self::SaleReturn => __('enums.stock_movement_type.sale_return'),
            self::PurchaseReturn => __('enums.stock_movement_type.purchase_return'),
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

    public function isPositive(): bool
    {
        return in_array($this, [self::TransferIn, self::Purchase, self::SaleReturn], true);
    }

    public function getIcon(): string|BackedEnum|null
    {
        return match ($this) {
            self::Adjustment => Heroicon::AdjustmentsHorizontal,
            self::TransferOut => Heroicon::ArrowRightOnRectangle,
            self::TransferIn => Heroicon::ArrowLeftOnRectangle,
            self::Loss => Heroicon::ExclamationTriangle,
            self::Damage => Heroicon::Fire,
            self::Purchase => Heroicon::ShoppingCart,
            self::Sale => Heroicon::CurrencyDollar,
            self::SaleReturn => Heroicon::ArrowUturnLeft,
            self::PurchaseReturn => Heroicon::ArrowUturnRight,
        };
    }
}
