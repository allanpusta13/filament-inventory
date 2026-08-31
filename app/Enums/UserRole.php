<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasColor, HasLabel
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
}
