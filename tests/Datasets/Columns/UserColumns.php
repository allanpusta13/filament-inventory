<?php

declare(strict_types=1);

namespace Tests\Datasets\Columns;

class UserColumns
{
    public static function index(): array
    {
        return [
            'name',
            'email',
            'role',
            'created_at',
        ];
    }

    public static function sortable(): array
    {
        return ['name', 'email', 'role', 'created_at'];
    }

    public static function filterable(): array
    {
        return ['role'];
    }

    public static function searchable(): array
    {
        return ['name', 'email'];
    }

    public static function create(): array
    {
        return ['name', 'email', 'password', 'role'];
    }

    public static function edit(): array
    {
        return ['name', 'email', 'password', 'role'];
    }

    public static function view(): array
    {
        return ['name', 'email', 'role', 'created_at'];
    }
}