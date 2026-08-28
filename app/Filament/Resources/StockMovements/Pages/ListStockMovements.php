<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockMovements\Pages;

use App\Filament\Resources\StockMovements\StockMovementResource;
use App\Traits\StockActions;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

final class ListStockMovements extends ListRecords
{
    use StockActions;

    protected static string $resource = StockMovementResource::class;

    /**
     * @return list<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->receiveStockAction(),
            $this->shipStockAction(),
            $this->transferStockAction(),
            $this->adjustmentAction(),
        ];
    }
}
