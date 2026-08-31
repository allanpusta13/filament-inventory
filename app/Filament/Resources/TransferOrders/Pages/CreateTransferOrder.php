<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferOrders\Pages;

use App\Filament\Resources\TransferOrders\TransferOrderResource;
use App\Models\TransferOrder;
use Filament\Resources\Pages\CreateRecord;

final class CreateTransferOrder extends CreateRecord
{
    protected static string $resource = TransferOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['reference_number'] = TransferOrder::generateReferenceNumber();
        $data['status'] = 'draft';

        return $data;
    }
}
