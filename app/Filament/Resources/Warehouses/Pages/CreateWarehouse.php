<?php

declare(strict_types=1);

namespace App\Filament\Resources\Warehouses\Pages;

use App\Filament\Resources\Warehouses\WarehouseResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateWarehouse extends CreateRecord
{
    protected static string $resource = WarehouseResource::class;
}
