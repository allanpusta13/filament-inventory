<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalWidth(\Filament\Support\Enums\Width::Large),
        ];
    }

    protected function getTableBulkActions(): array
    {
        return [
            \Filament\Actions\DeleteBulkAction::make()
                ->authorize('deleteAny'),
            \Filament\Actions\RestoreBulkAction::make()
                ->authorize('restoreAny'),
        ];
    }
}
