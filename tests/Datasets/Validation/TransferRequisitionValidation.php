<?php

declare(strict_types=1);

namespace Tests\Datasets\Validation;

class TransferRequisitionValidation
{
    public static function create(): array
    {
        return [
            'reference_code required' => [
                'data' => ['reference_code' => null],
                'errors' => ['reference_code' => 'required'],
            ],
            'reference_code unique' => [
                'data' => ['reference_code' => 'DUPLICATE-REF'],
                'errors' => ['reference_code' => 'unique'],
            ],
            'from_warehouse_id required' => [
                'data' => ['from_warehouse_id' => null],
                'errors' => ['from_warehouse_id' => 'required'],
            ],
            'to_warehouse_id required' => [
                'data' => ['to_warehouse_id' => null],
                'errors' => ['to_warehouse_id' => 'required'],
            ],
            'different warehouses' => [
                'data' => ['from_warehouse_id' => 1, 'to_warehouse_id' => 1],
                'errors' => ['to_warehouse_id' => 'different'],
            ],
        ];
    }

    public static function update(): array
    {
        return [
            'reference_code required' => [
                'data' => ['reference_code' => null],
                'errors' => ['reference_code' => 'required'],
            ],
            'from_warehouse_id required' => [
                'data' => ['from_warehouse_id' => null],
                'errors' => ['from_warehouse_id' => 'required'],
            ],
            'to_warehouse_id required' => [
                'data' => ['to_warehouse_id' => null],
                'errors' => ['to_warehouse_id' => 'required'],
            ],
            'different warehouses' => [
                'data' => ['from_warehouse_id' => 1, 'to_warehouse_id' => 1],
                'errors' => ['to_warehouse_id' => 'different'],
            ],
        ];
    }
}