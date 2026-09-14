<?php

declare(strict_types=1);

namespace Tests\Datasets\Validation;

use Illuminate\Support\Str;

class WarehouseValidation
{
    public static function create(): array
    {
        return [
            'code required' => [
                'data' => ['code' => null],
                'errors' => ['code' => 'required'],
            ],
            'code unique' => [
                'data' => ['code' => 'DUPLICATE-CODE'],
                'errors' => ['code' => 'unique'],
            ],
            'code max 255' => [
                'data' => ['code' => Str::random(256)],
                'errors' => ['code' => 'max'],
            ],
            'name required' => [
                'data' => ['name' => null],
                'errors' => ['name' => 'required'],
            ],
            'name max 255' => [
                'data' => ['name' => Str::random(256)],
                'errors' => ['name' => 'max'],
            ],
        ];
    }

    public static function update(): array
    {
        return [
            'code required' => [
                'data' => ['code' => null],
                'errors' => ['code' => 'required'],
            ],
            'code unique' => [
                'data' => ['code' => 'DUPLICATE-CODE'],
                'errors' => ['code' => 'unique'],
            ],
            'name required' => [
                'data' => ['name' => null],
                'errors' => ['name' => 'required'],
            ],
        ];
    }
}