<?php

declare(strict_types=1);

namespace App\Filament\Resources\LossLedgers\Pages;

use App\Filament\Resources\LossLedgers\LossLedgerResource;
use Filament\Resources\Pages\ListRecords;

class ListLossLedgers extends ListRecords
{
    protected static string $resource = LossLedgerResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
