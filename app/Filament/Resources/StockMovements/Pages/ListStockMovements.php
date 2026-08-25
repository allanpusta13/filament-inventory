<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockMovements\Pages;

use App\Filament\Resources\StockMovements\StockMovementResource;
use Filament\Resources\Pages\ListRecords;

final class ListStockMovements extends ListRecords
{
    protected static string $resource = StockMovementResource::class;
}
