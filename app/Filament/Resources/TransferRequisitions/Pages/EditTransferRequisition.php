<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferRequisitions\Pages;

use App\Filament\Resources\TransferRequisitions\TransferRequisitionResource;
use Filament\Resources\Pages\EditRecord;

class EditTransferRequisition extends EditRecord
{
    protected static string $resource = TransferRequisitionResource::class;

    public function getModalWidth(): string
    {
        return 'large';
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\DeleteAction::make(),
        ];
    }
}
