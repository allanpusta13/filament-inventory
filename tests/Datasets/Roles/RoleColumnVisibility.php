<?php

declare(strict_types=1);

namespace Tests\Datasets\Roles;

use App\Enums\UserRole;

class RoleColumnVisibility
{
    /** @return array<string, array<string, UserRole>> */
    public static function expectedVisible(): array
    {
        return [
            'index' => [
                'reference_code' => UserRole::ADMIN,
                'sku' => UserRole::ADMIN,
                'name' => UserRole::ADMIN,
                'status' => UserRole::ADMIN,
                'created_at' => UserRole::ADMIN,
            ],
            'create' => [
                'sku' => UserRole::ADMIN,
                'name' => UserRole::ADMIN,
                'base_unit_name' => UserRole::ADMIN,
                'reorder_point' => UserRole::ADMIN,
                'attributes' => UserRole::ADMIN,
                'is_active' => UserRole::ADMIN,
            ],
            'edit' => [
                'sku' => UserRole::ADMIN,
                'name' => UserRole::ADMIN,
                'base_unit_name' => UserRole::ADMIN,
                'reorder_point' => UserRole::ADMIN,
                'attributes' => UserRole::ADMIN,
                'is_active' => UserRole::ADMIN,
            ],
            'view' => [
                'product.name' => UserRole::ADMIN,
                'sku' => UserRole::ADMIN,
                'barcode' => UserRole::ADMIN,
                'name' => UserRole::ADMIN,
                'currentPrice.cost_price' => UserRole::ADMIN,
                'currentPrice.sale_price' => UserRole::ADMIN,
                'unitConversions' => UserRole::ADMIN,
            ],
        ];
    }

    /** @return array<string, array<string, UserRole>> */
    public static function expectedHidden(): array
    {
        return [
            'index' => [],
            'create' => [],
            'edit' => [],
            'view' => [],
        ];
    }

    /** @return array<string, array<string, UserRole>> */
    public static function warehouseScoped(): array
    {
        return [
            'index' => [
                'reference_code' => UserRole::WAREHOUSE_STAFF,
                'fromWarehouse.name' => UserRole::WAREHOUSE_STAFF,
                'toWarehouse.name' => UserRole::WAREHOUSE_STAFF,
                'status' => UserRole::WAREHOUSE_STAFF,
                'requested_at' => UserRole::WAREHOUSE_STAFF,
            ],
        ];
    }
}
