<?php

declare(strict_types=1);

namespace App\Filament\Resources\LossLedgers\Pages;

use App\Filament\Resources\LossLedgers\LossLedgerResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;

class ViewLossLedger extends ViewRecord
{
    protected static string $resource = LossLedgerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
