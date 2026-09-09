<?php

declare(strict_types=1);

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

enum UserRole: string implements HasColor, HasIcon, HasLabel
{
    case ADMIN = 'admin';
    case AUDITOR = 'auditor';
    case BRANCH_MANAGER = 'branch_manager';
    case WAREHOUSE_STAFF = 'warehouse_staff';

    public function getLabel(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrator',
            self::AUDITOR => 'Logistics Auditor',
            self::BRANCH_MANAGER => 'Branch Manager',
            self::WAREHOUSE_STAFF => 'Warehouse Staff',
        };
    }

    public function getIcon(): string|BackedEnum|Htmlable|null
    {
        return match ($this) {
            self::ADMIN => Heroicon::OutlinedUserCircle,
            self::AUDITOR => Heroicon::OutlinedEye,
            self::BRANCH_MANAGER => Heroicon::OutlinedBuildingOffice2,
            self::WAREHOUSE_STAFF => Heroicon::OutlinedTruck,
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::ADMIN => 'danger',
            self::AUDITOR => 'info',
            self::BRANCH_MANAGER => 'warning',
            self::WAREHOUSE_STAFF => 'gray',
        };
    }
}
