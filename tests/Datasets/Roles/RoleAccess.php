<?php

declare(strict_types=1);

namespace Tests\Datasets\Roles;

use App\Enums\UserRole;

class RoleAccess
{
    /** @return array<string, UserRole> */
    public static function all(): array
    {
        return [
            'admin' => UserRole::ADMIN,
            'auditor' => UserRole::AUDITOR,
            'branch_manager' => UserRole::BRANCH_MANAGER,
            'warehouse_staff' => UserRole::WAREHOUSE_STAFF,
        ];
    }

    /** @return array<string, UserRole> */
    public static function withWarehouseAccess(): array
    {
        return [
            'admin' => UserRole::ADMIN,
            'auditor' => UserRole::AUDITOR,
            'branch_manager' => UserRole::BRANCH_MANAGER,
            'warehouse_staff' => UserRole::WAREHOUSE_STAFF,
        ];
    }

    /** @return array<string, UserRole> */
    public static function adminOnly(): array
    {
        return ['admin' => UserRole::ADMIN];
    }

    /** @return array<string, UserRole> */
    public static function nonAdmin(): array
    {
        return [
            'auditor' => UserRole::AUDITOR,
            'branch_manager' => UserRole::BRANCH_MANAGER,
            'warehouse_staff' => UserRole::WAREHOUSE_STAFF,
        ];
    }

    /** @return array<string, UserRole> */
    public static function managers(): array
    {
        return [
            'admin' => UserRole::ADMIN,
            'auditor' => UserRole::AUDITOR,
            'branch_manager' => UserRole::BRANCH_MANAGER,
        ];
    }
}
