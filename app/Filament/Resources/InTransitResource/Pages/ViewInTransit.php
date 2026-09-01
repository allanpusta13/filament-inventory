<?php

declare(strict_types=1);

namespace App\Filament\Resources\InTransitResource\Pages;

use App\Filament\Resources\InTransitResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewInTransit extends ViewRecord
{
    protected static string $resource = InTransitResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
