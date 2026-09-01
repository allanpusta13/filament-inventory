<?php

declare(strict_types=1);

namespace App\Filament\Resources\LossLedgerResource\Pages;

use App\Filament\Resources\LossLedgerResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewLossLedger extends ViewRecord
{
    protected static string $resource = LossLedgerResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
