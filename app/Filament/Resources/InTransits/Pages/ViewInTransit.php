<?php

declare(strict_types=1);

namespace App\Filament\Resources\InTransits\Pages;

use App\Filament\Resources\InTransits\InTransitResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;

class ViewInTransit extends ViewRecord
{
    protected static string $resource = InTransitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
