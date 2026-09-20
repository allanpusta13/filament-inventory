<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockMovements\Pages;

use App\Filament\Resources\StockMovements\StockMovementResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewStockMovement extends ViewRecord
{
    protected static string $resource = StockMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('skipLink')
                ->hiddenLabel()
                ->icon('heroicon-s-arrow-right')
                ->extraAttributes([
                    'href' => '#main-content',
                    'class' => 'fi-skip-link fi-sr-only focus:not-sr-only',
                    'tabindex' => '0',
                ])
                ->action(fn () => null),
        ];
    }
}
