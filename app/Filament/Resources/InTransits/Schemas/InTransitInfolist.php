<?php

declare(strict_types=1);

namespace App\Filament\Resources\InTransits\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class InTransitInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'md' => 3, 'xl' => 3])->schema([
                Section::make('IN-TRANSIT CARGO')
                    ->icon(Heroicon::Truck)
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'md' => 3, 'xl' => 3])
                    ->schema([
                        TextEntry::make('transferRequisition.reference_code')
                            ->label(__('resources.in_transits.fields.requisition'))
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('productVariant.sku')
                            ->label(__('resources.in_transits.fields.sku'))
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('dispatched_base_qty')
                            ->label(__('resources.in_transits.fields.dispatched_base'))
                            ->numeric()
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('dispatched_at')
                            ->label(__('resources.in_transits.fields.dispatched_at'))
                            ->dateTime('M j, Y H:i')
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('status')
                            ->badge()
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('cleared_at')
                            ->label(__('resources.in_transits.fields.cleared_at'))
                            ->dateTime('M j, Y H:i')
                            ->placeholder('—')
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                    ]),
            ]),
        ]);
    }
}
