<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferOrders\Pages;

use App\Filament\Resources\TransferOrders\TransferOrderResource;
use Filament\Resources\Pages\EditRecord;

final class EditTransferOrder extends EditRecord
{
    protected static string $resource = TransferOrderResource::class;

    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        abort_unless($this->getRecord()->status->value === 'draft', 403);
    }
}
