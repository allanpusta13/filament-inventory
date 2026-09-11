<?php

declare(strict_types=1);

namespace App\Filament\Resources\DirectTransfers\Pages;

use App\Filament\Resources\DirectTransfers\DirectTransferResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDirectTransfers extends ListRecords
{
    protected static string $resource = DirectTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalWidth(\Filament\Support\Enums\Width::MaxContent),
        ];
    }
}