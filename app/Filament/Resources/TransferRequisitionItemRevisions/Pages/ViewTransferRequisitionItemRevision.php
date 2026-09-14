<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferRequisitionItemRevisions\Pages;

use App\Filament\Resources\TransferRequisitionItemRevisions\TransferRequisitionItemRevisionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTransferRequisitionItemRevision extends ViewRecord
{
    protected static string $resource = TransferRequisitionItemRevisionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
