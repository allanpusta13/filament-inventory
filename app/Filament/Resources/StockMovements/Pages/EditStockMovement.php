<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockMovements\Pages;

use App\Filament\Resources\StockMovements;
use Filament\Resources\Pages\EditRecord;

final class EditStockMovement extends EditRecord
{
    protected static string $resource = StockMovements::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
