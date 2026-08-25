<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;

enum MovementType: string implements HasColor
{
    case Receive = 'receive';
    case Ship = 'ship';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case Adjustment = 'adjustment';

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Receive, self::TransferIn => 'success',
            self::Ship, self::TransferOut => 'danger',
            self::Adjustment => 'warning',
        };
    }
}
