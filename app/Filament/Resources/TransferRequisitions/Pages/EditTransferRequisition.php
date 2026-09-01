<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferRequisitions\Pages;

use App\Filament\Resources\TransferRequisitions\TransferRequisitionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditTransferRequisition extends EditRecord
{
    protected static string $resource = TransferRequisitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
