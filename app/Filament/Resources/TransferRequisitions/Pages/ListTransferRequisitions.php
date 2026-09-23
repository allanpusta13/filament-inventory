<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferRequisitions\Pages;

use App\Filament\Resources\TransferRequisitions\TransferRequisitionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTransferRequisitions extends ListRecords
{
    protected static string $resource = TransferRequisitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('NEW TRANSFER REQUEST')
                ->icon(\Filament\Support\Icons\Heroicon::Plus),
        ];
    }
}
