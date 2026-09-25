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
            self::ADMIN => __('enums.user_role.admin'),
            self::AUDITOR => __('enums.user_role.auditor'),
            self::WAREHOUSE_STAFF => __('enums.user_role.warehouse_staff'),
            self::BRANCH_MANAGER => __('enums.user_role.branch_manager'),
            self::GUEST => __('enums.user_role.guest')
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
