<?php

declare(strict_types=1);

namespace Tests\Datasets\Columns;

class TransferRequisitionColumns
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
            'fromWarehouse.name',
            'toWarehouse.name',
            'status',
            'requested_at',
            'completed_at',
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
            'fromWarehouse.name',
            'toWarehouse.name',
            'status',
            'requested_at',
            'completed_at',
        ];
    }

    /**
     * Columns available for filtering.
     *
     * @return array<int, string>
     */
    public static function filterable(): array
    {
        return ['status', 'from_warehouse_id', 'to_warehouse_id'];
    }

    /**
     * Columns available for searching.
     *
     * @return array<int, string>
     */
    public static function searchable(): array
    {
        return ['reference_code', 'fromWarehouse.name', 'toWarehouse.name'];
    }

    /**
     * Fields displayed on view page.
     *
     * @return array<int, string>
     */
    public static function view(): array
    {
        return [
            'reference_code',
            'status',
            'fromWarehouse.name',
            'toWarehouse.name',
            'requestedBy.name',
            'approvedBy.name',
            'dispatchedBy.name',
            'receivedBy.name',
            'requested_at',
            'approved_at',
            'dispatched_at',
            'completed_at',
            'notes',
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