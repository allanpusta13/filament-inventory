<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferRequisitionItemRevisions\Pages;

use App\Filament\Resources\TransferRequisitionItemRevisions\TransferRequisitionItemRevisionResource;
use Filament\Resources\Pages\ListRecords;

class ListTransferRequisitionItemRevisions extends ListRecords
{
    protected static string $resource = TransferRequisitionItemRevisionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}