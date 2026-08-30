<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;

enum TransferOrderItemStatus: string implements HasColor
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
}
