<?php

declare(strict_types=1);

namespace App\Filament\Resources\InTransits\Pages;

use App\Filament\Resources\InTransits\InTransitResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewInTransit extends ViewRecord
{
    protected static string $resource = InTransitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('receiveIntake')
                ->label('RECEIVE INTAKE')
                ->icon(\Filament\Support\Icons\Heroicon::QrCode)
                ->color('success')
                ->url(fn ($record) => $record->getUrl('scan-to-receive')),
        ];
    }
}