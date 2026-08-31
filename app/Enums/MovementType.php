<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MovementType: string implements HasColor, HasLabel
{
    case Receive = 'receive';
    case Ship = 'ship';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case TransitOut = 'transit_out';
    case TransitIn = 'transit_in';
    case Adjustment = 'adjustment';
    case Loss = 'loss';

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Receive, self::TransferIn, self::TransitIn => 'success',
            self::Ship, self::TransferOut, self::TransitOut => 'danger',
            self::Adjustment => 'warning',
            self::Loss => 'danger',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Receive => 'Receive',
            self::Ship => 'Ship',
            self::TransferOut => 'Transfer Out',
            self::TransferIn => 'Transfer In',
            self::TransitOut => 'Transit Out',
            self::TransitIn => 'Transit In',
            self::Adjustment => 'Adjustment',
            self::Loss => 'Loss',
        };
    }
}
