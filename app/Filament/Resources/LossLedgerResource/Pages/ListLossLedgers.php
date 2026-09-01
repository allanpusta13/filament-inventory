<?php

declare(strict_types=1);

namespace App\Filament\Resources\LossLedgerResource\Pages;

use App\Filament\Resources\LossLedgerResource;
use Filament\Resources\Pages\ListRecords;

final class ListLossLedgers extends ListRecords
{
    protected static string $resource = LossLedgerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action since it's read-only
        ];
    }
}
