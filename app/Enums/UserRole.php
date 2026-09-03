<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasIcon;

enum UserRole: string implements HasColor, HasLabel, HasIcon
{
    case Admin = 'admin';
    case BranchManager = 'branch_manager';
    case WarehouseStaff = 'warehouse_staff';
    case Auditor = 'auditor';

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Admin => 'danger',
            self::BranchManager => 'warning',
            self::WarehouseStaff => 'success',
            self::Auditor => 'gray',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::BranchManager => 'Branch Manager',
            self::WarehouseStaff => 'Warehouse Staff',
            self::Auditor => 'Auditor',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Admin => 'heroicon-o-user',
            self::BranchManager => 'heroicon-o-briefcase',
            self::WarehouseStaff => 'heroicon-o-user-group',
            self::Auditor => 'heroicon-o-clipboard-list',
        };
    }
}
