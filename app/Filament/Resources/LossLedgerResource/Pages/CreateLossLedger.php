<?php

declare(strict_types=1);

namespace App\Filament\Resources\LossLedgerResource\Pages;

use App\Filament\Resources\LossLedgerResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateLossLedger extends CreateRecord
{
    protected static string $resource = LossLedgerResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
