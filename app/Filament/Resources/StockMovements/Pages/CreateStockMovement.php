<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockMovements\Pages;

use App\Filament\Resources\StockMovements\StockMovementResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateStockMovement extends CreateRecord
{
    protected static string $resource = StockMovementResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
