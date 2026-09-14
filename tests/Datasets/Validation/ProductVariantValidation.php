<?php

declare(strict_types=1);

namespace Tests\Datasets\Validation;

use Illuminate\Support\Str;

class ProductVariantValidation
{
    public static function create(): array
    {
        return [
            'sku required' => [
                'data' => ['sku' => null],
                'errors' => ['sku' => 'required'],
            ],
            'sku max 255' => [
                'data' => ['sku' => Str::random(256)],
                'errors' => ['sku' => 'max'],
            ],
            'sku unique' => [
                'data' => ['sku' => 'DUPLICATE-SKU'],
                'errors' => ['sku' => 'unique'],
            ],
            'name required' => [
                'data' => ['name' => null],
                'errors' => ['name' => 'required'],
            ],
            'base_unit_name required' => [
                'data' => ['base_unit_name' => null],
                'errors' => ['base_unit_name' => 'required'],
            ],
            'reorder_point required' => [
                'data' => ['reorder_point' => null],
                'errors' => ['reorder_point' => 'required'],
            ],
            'reorder_point numeric' => [
                'data' => ['reorder_point' => 'abc'],
                'errors' => ['reorder_point' => 'numeric'],
            ],
            'barcode max 255' => [
                'data' => ['barcode' => Str::random(256)],
                'errors' => ['barcode' => 'max'],
            ],
        ];
    }

    public static function update(): array
    {
        return [
            'sku required' => [
                'data' => ['sku' => null],
                'errors' => ['sku' => 'required'],
            ],
            'sku max 255' => [
                'data' => ['sku' => Str::random(256)],
                'errors' => ['sku' => 'max'],
            ],
            'name required' => [
                'data' => ['name' => null],
                'errors' => ['name' => 'required'],
            ],
            'base_unit_name required' => [
                'data' => ['base_unit_name' => null],
                'errors' => ['base_unit_name' => 'required'],
            ],
            'reorder_point required' => [
                'data' => ['reorder_point' => null],
                'errors' => ['reorder_point' => 'required'],
            ],
            'reorder_point numeric' => [
                'data' => ['reorder_point' => 'abc'],
                'errors' => ['reorder_point' => 'numeric'],
            ],
            'barcode max 255' => [
                'data' => ['barcode' => Str::random(256)],
                'errors' => ['barcode' => 'max'],
            ],
        ];
    }
}