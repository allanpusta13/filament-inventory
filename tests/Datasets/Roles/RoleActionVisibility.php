<?php

declare(strict_types=1);

namespace Tests\Datasets\Roles;

use App\Enums\UserRole;

class RoleActionVisibility
{
    /** @return array<string, array<string, UserRole>> */
    public static function indexActions(): array
    {
        return [
            'view' => [
                'admin' => UserRole::ADMIN,
                'auditor' => UserRole::AUDITOR,
                'branch_manager' => UserRole::BRANCH_MANAGER,
                'warehouse_staff' => UserRole::WAREHOUSE_STAFF,
            ],
            'edit' => [
                'admin' => UserRole::ADMIN,
            ],
            'delete' => [
                'admin' => UserRole::ADMIN,
            ],
            'restore' => [
                'admin' => UserRole::ADMIN,
            ],
        ];
    }

    /** @return array<string, array<string, UserRole>> */
    public static function viewActions(): array
    {
        return [
            'edit' => [
                'admin' => UserRole::ADMIN,
            ],
            'delete' => [
                'admin' => UserRole::ADMIN,
            ],
            'restore' => [
                'admin' => UserRole::ADMIN,
            ],
            'confirm' => [
                'admin' => UserRole::ADMIN,
                'branch_manager' => UserRole::BRANCH_MANAGER,
            ],
            'dispatch' => [
                'admin' => UserRole::ADMIN,
                'branch_manager' => UserRole::BRANCH_MANAGER,
            ],
            'receive' => [
                'admin' => UserRole::ADMIN,
                'branch_manager' => UserRole::BRANCH_MANAGER,
                'warehouse_staff' => UserRole::WAREHOUSE_STAFF,
            ],
            'cancel' => [
                'admin' => UserRole::ADMIN,
                'branch_manager' => UserRole::BRANCH_MANAGER,
            ],
        ];
    }

    /** @return array<string, array<string, UserRole>> */
    public static function bulkActions(): array
    {
        return [
            'delete' => [
                'admin' => UserRole::ADMIN,
            ],
            'restore' => [
                'admin' => UserRole::ADMIN,
            ],
            'forceDelete' => [
                'admin' => UserRole::ADMIN,
            ],
        ];
    }
}
