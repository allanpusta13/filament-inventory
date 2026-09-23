<?php

declare(strict_types=1);

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum UserRole: string implements HasColor, HasIcon, HasLabel
{
    case ADMIN = 'admin';
    case AUDITOR = 'auditor';
    case BRANCH_MANAGER = 'branch_manager';
    case WAREHOUSE_STAFF = 'warehouse_staff';
    case GUEST = 'guest';

    public function getLabel(): string
    {
        return match ($this) {
            self::ADMIN => __('Administrator'),
            self::AUDITOR => __('Logistics Auditor'),
            self::BRANCH_MANAGER => __('Branch Manager'),
            self::WAREHOUSE_STAFF => __('Warehouse Staff'),
            self::GUEST => __('Guest'),
        };
    }

    public function getIcon(): string|BackedEnum|null
    {
        return match ($this) {
            self::ADMIN => Heroicon::OutlinedUserCircle,
            self::AUDITOR => Heroicon::OutlinedEye,
            self::BRANCH_MANAGER => Heroicon::OutlinedBuildingOffice2,
            self::WAREHOUSE_STAFF => Heroicon::OutlinedTruck,
            self::GUEST => Heroicon::OutlinedUser,
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::ADMIN => 'danger',
            self::AUDITOR => 'info',
            self::BRANCH_MANAGER => 'warning',
            self::WAREHOUSE_STAFF => 'gray',
            self::GUEST => 'zinc',
        };
    }
}
