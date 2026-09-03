<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasIcon;

enum TransferOrderItemStatus: string implements HasColor, HasLabel, HasIcon
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Modified = 'modified';
    case Added = 'added';
    case Removed = 'removed';

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Requested => 'gray',
            self::Approved => 'success',
            self::Modified => 'warning',
            self::Added => 'info',
            self::Removed => 'danger',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Requested => 'Requested',
            self::Approved => 'Approved',
            self::Modified => 'Modified',
            self::Added => 'Added',
            self::Removed => 'Removed',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Requested => 'heroicon-o-clock',
            self::Approved => 'heroicon-o-check-circle',
            self::Modified => 'heroicon-o-pencil',
            self::Added => 'heroicon-o-plus-circle',
            self::Removed => 'heroicon-o-minus-circle',
        };
    }
}
