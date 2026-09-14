<?php

declare(strict_types=1);

namespace Tests\Datasets\Columns;

class InTransitColumns
{
    /**
     * Columns displayed on the index page.
     *
     * @return array<int, string>
     */
    public static function index(): array
    {
        return [
            'transferRequisition.reference_code',
            'productVariant.sku',
            'productVariant.name',
            'status',
            'dispatched_base_qty',
            'dispatched_at',
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
            'transferRequisition.reference_code',
            'productVariant.sku',
            'productVariant.name',
            'status',
            'dispatched_base_qty',
            'dispatched_at',
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
        return ['status', 'transfer_requisition_id', 'product_variant_id'];
    }

    /**
     * Columns available for searching.
     *
     * @return array<int, string>
     */
    public static function searchable(): array
    {
        return ['transferRequisition.reference_code', 'productVariant.sku', 'productVariant.name'];
    }

    /**
     * Fields displayed on view page.
     *
     * @return array<int, string>
     */
    public static function view(): array
    {
        return [
            'transferRequisition.reference_code',
            'productVariant.sku',
            'productVariant.name',
            'status',
            'dispatched_base_qty',
            'dispatched_at',
            'created_at',
        ];
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
            'view' => self::view(),
        ];
    }
}
