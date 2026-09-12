<?php

declare(strict_types=1);

namespace App\Filament\Resources\Warehouses\Pages;

use App\Filament\Resources\Warehouses\WarehouseResource;
use Filament\Resources\Pages\EditRecord;

final class EditWarehouse extends EditRecord
{
    protected static string $resource = WarehouseResource::class;

    public function getDrawerWidth(): string
    {
        return 'large';
    }

    public function getModalWidth(): string
    {
        return 'large';
    }
}
