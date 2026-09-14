<?php

declare(strict_types=1);

namespace Tests\Datasets\Columns;

class WarehouseColumns
{
    public static function index(): array
    {
        return [
            'code',
            'name',
            'location',
            'is_active',
        ];
    }

    public static function sortable(): array
    {
        return ['code', 'name', 'location', 'is_active'];
    }

    public static function filterable(): array
    {
        return ['is_active'];
    }

    public static function searchable(): array
    {
        return ['code', 'name', 'location'];
    }

    public static function create(): array
    {
        return ['code', 'name', 'location', 'is_active'];
    }

    public static function edit(): array
    {
        return ['code', 'name', 'location', 'is_active'];
    }

    public static function view(): array
    {
        return ['code', 'name', 'location', 'is_active'];
    }
}
