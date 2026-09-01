<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferRequisitions\Pages;

use App\Filament\Resources\TransferRequisitions\TransferRequisitionResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateTransferRequisition extends CreateRecord
{
    protected static string $resource = TransferRequisitionResource::class;
}
