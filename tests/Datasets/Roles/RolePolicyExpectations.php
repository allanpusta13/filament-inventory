<?php

declare(strict_types=1);

namespace Tests\Datasets\Roles;

class RolePolicyExpectations
{
    /** @return array<string, array<string, bool>> */
    public static function policyResults(): array
    {
        return [
            'viewAny' => [
                'admin' => true,
                'auditor' => true,
                'branch_manager' => true,
                'warehouse_staff' => true,
            ],
            'view' => [
                'admin' => true,
                'auditor' => true,
                'branch_manager' => true,
                'warehouse_staff' => true,
            ],
            'create' => [
                'admin' => true,
                'auditor' => false,
                'branch_manager' => false,
                'warehouse_staff' => false,
            ],
            'update' => [
                'admin' => true,
                'auditor' => false,
                'branch_manager' => false,
                'warehouse_staff' => false,
            ],
            'delete' => [
                'admin' => true,
                'auditor' => false,
                'branch_manager' => false,
                'warehouse_staff' => false,
            ],
            'restore' => [
                'admin' => true,
                'auditor' => true,
                'branch_manager' => false,
                'warehouse_staff' => false,
            ],
            'forceDelete' => [
                'admin' => true,
                'auditor' => false,
                'branch_manager' => false,
                'warehouse_staff' => false,
            ],
            'confirm' => [
                'admin' => true,
                'auditor' => false,
                'branch_manager' => true,
                'warehouse_staff' => false,
            ],
            'dispatch' => [
                'admin' => true,
                'auditor' => false,
                'branch_manager' => true,
                'warehouse_staff' => false,
            ],
            'receive' => [
                'admin' => true,
                'auditor' => false,
                'branch_manager' => true,
                'warehouse_staff' => true,
            ],
            'cancel' => [
                'admin' => true,
                'auditor' => false,
                'branch_manager' => true,
                'warehouse_staff' => false,
            ],
            'setPrice' => [
                'admin' => true,
                'auditor' => false,
                'branch_manager' => false,
                'warehouse_staff' => false,
            ],
            'adjustStock' => [
                'admin' => true,
                'auditor' => false,
                'branch_manager' => false,
                'warehouse_staff' => true,
            ],
            'recordLoss' => [
                'admin' => true,
                'auditor' => false,
                'branch_manager' => false,
                'warehouse_staff' => false,
            ],
        ];
    }
}
