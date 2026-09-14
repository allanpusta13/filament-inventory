<?php

declare(strict_types=1);

namespace Tests\Datasets\Columns;

class DirectTransferColumns
{
    /**
     * Columns displayed on the index page.
     *
     * @return array<int, string>
     */
    public static function index(): array
    {
        return [
            'reference_code',
            'productVariant.sku',
            'productVariant.name',
            'warehouse.name',
            'type',
            'quantity',
            'created_at',
        ];
    }

    /**
     * Columns available for sorting.
     *
     * @return array<int, string>
     */
    public static function sortable(): array
    {
        return [
            'reference_code',
            'productVariant.sku',
            'productVariant.name',
            'warehouse.name',
            'type',
            'quantity',
            'created_at',
        ];
    }

    /**
     * Columns available for filtering.
     *
     * @return array<int, string>
     */
    public static function filterable(): array
    {
        return ['type', 'warehouse_id'];
    }

    /**
     * Columns available for searching.
     *
     * @return array<int, string>
     */
    public static function searchable(): array
    {
        return ['reference_code', 'productVariant.sku', 'productVariant.name'];
    }

    /**
     * Get all column sets for parametrized testing.
     *
     * @return array<string, array<int, string>>
     */
    public static function allSets(): array
    {
        return [
            'index' => self::index(),
            'sortable' => self::sortable(),
            'filterable' => self::filterable(),
            'searchable' => self::searchable(),
        ];
    }
}
