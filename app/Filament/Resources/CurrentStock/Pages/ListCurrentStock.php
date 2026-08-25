<?php

declare(strict_types=1);

namespace App\Filament\Resources\CurrentStock\Pages;

use App\Filament\Resources\CurrentStock\CurrentStockResource;
use Filament\Resources\Pages\ListRecords;

final class ListCurrentStock extends ListRecords
{
    protected static string $resource = CurrentStockResource::class;
}
