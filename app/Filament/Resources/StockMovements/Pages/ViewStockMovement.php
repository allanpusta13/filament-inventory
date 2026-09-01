<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockMovements\Pages;

use App\Filament\Resources\StockMovements;
use Filament\Resources\Pages\ViewRecord;

final class ViewStockMovement extends ViewRecord
{
    protected static string $resource = StockMovements::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
