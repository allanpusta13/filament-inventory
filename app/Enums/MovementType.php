<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum MovementType: string implements HasColor, HasIcon, HasLabel
{
    case Receive = 'receive';
    case Ship = 'ship';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case TransitOut = 'transit_out';
    case TransitIn = 'transit_in';
    case Adjustment = 'adjustment';
    case Loss = 'loss';

    public function isInbound(): bool
    {
        return match ($this) {
            self::Receive, self::TransferIn, self::TransitIn => true,
            default => false,
        };
    }

    public function isOutbound(): bool
    {
        return match ($this) {
            self::Ship, self::TransferOut, self::TransitOut, self::Loss => true,
            default => false,
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Receive, self::TransferIn, self::TransitIn => 'success',
            self::Ship, self::TransferOut, self::TransitOut => 'orange',
            self::Adjustment => 'blue',
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

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Receive => 'heroicon-o-arrow-down',
            self::Ship => 'heroicon-o-arrow-up',
            self::TransferOut, self::TransferIn => 'heroicon-o-arrows-left-right',
            self::TransitOut, self::TransitIn => 'heroicon-o-arrow-path',
            self::Adjustment => 'heroicon-o-pencil',
            self::Loss => 'heroicon-o-exclamation-triangle',
        };
    }
}
