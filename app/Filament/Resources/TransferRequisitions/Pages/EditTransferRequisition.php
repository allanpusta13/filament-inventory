<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferRequisitions\Pages;

use App\Filament\Resources\TransferRequisitions\TransferRequisitionResource;
use Filament\Resources\Pages\EditRecord;

class EditTransferRequisition extends EditRecord
{
    protected static string $resource = TransferRequisitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\DeleteAction::make(),
        ];
    }

    public function getModalWidth(): string
    {
        return 'large';
    }
}