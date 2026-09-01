<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferRequisitions\Pages;

use App\Filament\Resources\TransferRequisitions\TransferRequisitionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListTransferRequisitions extends ListRecords
{
    protected static string $resource = TransferRequisitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
