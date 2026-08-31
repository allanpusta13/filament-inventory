<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferOrders\Pages;

use App\Filament\Resources\TransferOrders\TransferOrderResource;
use Filament\Resources\Pages\ListRecords;

final class ListTransferOrders extends ListRecords
{
    protected static string $resource = TransferOrderResource::class;
}
