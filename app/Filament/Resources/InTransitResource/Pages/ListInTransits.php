<?php

declare(strict_types=1);

namespace App\Filament\Resources\InTransitResource\Pages;

use App\Filament\Resources\InTransitResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

final class ListInTransits extends ListRecords
{
    protected static string $resource = InTransitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
