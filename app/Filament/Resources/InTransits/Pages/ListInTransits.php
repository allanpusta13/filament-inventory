<?php

declare(strict_types=1);

namespace App\Filament\Resources\InTransits\Pages;

use App\Filament\Resources\InTransits\InTransitResource;
use Filament\Resources\Pages\ListRecords;

class ListInTransits extends ListRecords
{
    protected static string $resource = InTransitResource::class;

    // No getHeaderActions() — read-only monitor, no create route.
}
