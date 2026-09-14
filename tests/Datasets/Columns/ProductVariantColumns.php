<?php

declare(strict_types=1);

namespace Tests\Datasets\Columns;

class ProductVariantColumns
{
    /**
     * Columns displayed on the index page.
     *
     * @return array<int, string>
     */
    public static function index(): array
    {
        return [
            'product.name',
            'sku',
            'barcode',
            'name',
            'base_unit_name',
            'currentPrice.sale_price',
            'reorder_point',
            'is_active',
        ];
    }

    /**
     * Columns available for searching.
     *
     * @return array<int, string>
     */
    public static function searchable(): array
    {
        return ['product.name', 'sku', 'barcode', 'name'];
    }

    /**
     * Columns available for filtering.
     *
     * @return array<int, string>
     */
    public static function filterable(): array
    {
        return ['product_id', 'is_active'];
    }

    /**
     * Columns available for sorting.
     *
     * @return array<int, string>
     */
    public static function sortable(): array
    {
        return ['product.name', 'sku', 'name', 'reorder_point'];
    }

    /**
     * Fields available on create form.
     *
     * @return array<int, string>
     */
    public static function create(): array
    {
        return [
            'product_id',
            'sku',
            'barcode',
            'name',
            'base_unit_name',
            'reorder_point',
            'attributes',
            'images',
            'is_active',
        ];
    }

    /**
     * Fields available on edit form.
     *
     * @return array<int, string>
     */
    public static function edit(): array
    {
        return [
            'product_id',
            'sku',
            'barcode',
            'name',
            'base_unit_name',
            'reorder_point',
            'attributes',
            'images',
            'is_active',
        ];
    }

    /**
     * Fields displayed on view page.
     *
     * @return array<int, string>
     */
    public static function view(): array
    {
        return [
            'product.name',
            'sku',
            'barcode',
            'name',
            'currentPrice.cost_price',
            'currentPrice.sale_price',
            'unitConversions',
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
            'searchable' => self::searchable(),
            'filterable' => self::filterable(),
            'sortable' => self::sortable(),
            'create' => self::create(),
            'edit' => self::edit(),
            'view' => self::view(),
        ];
    }
}
