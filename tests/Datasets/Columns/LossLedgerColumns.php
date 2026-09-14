<?php

declare(strict_types=1);

namespace Tests\Datasets\Columns;

class LossLedgerColumns
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
            'warehouse.name',
            'loss_category',
            'lost_base_qty',
            'damaged_base_qty',
            'total_financial_loss',
            'recorded_at',
            'recordedBy.name',
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
            'warehouse.name',
            'loss_category',
            'lost_base_qty',
            'damaged_base_qty',
            'total_financial_loss',
            'recorded_at',
            'recordedBy.name',
        ];
    }

    /**
     * Columns available for filtering.
     *
     * @return array<int, string>
     */
    public static function filterable(): array
    {
        return ['loss_category', 'warehouse_id', 'transfer_requisition_id'];
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
            'warehouse.name',
            'loss_category',
            'lost_base_qty',
            'damaged_base_qty',
            'unit_cost_price',
            'total_financial_loss',
            'recorded_at',
            'recordedBy.name',
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