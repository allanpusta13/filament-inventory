<?php

declare(strict_types=1);

namespace App\Filament\Resources\InTransits\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

class InTransitInfolist
{
    public static function configure($schema): object
    {
        return $schema->schema([
            \Filament\Schemas\Components\Grid::make(3)->schema([
                \Filament\Schemas\Components\Section::make('IN-TRANSIT IDENTITY')
                    ->icon(\Filament\Support\Icons\Heroicon::Truck)
                    ->schema([
                        \Filament\Schemas\Components\Grid::make(2)->schema([
                            \Filament\Infolists\Components\TextEntry::make('transferRequisition.reference_code')
                                ->label('REQUISITION REF')
                                ->weight(\Filament\Support\Enums\FontWeight::Bold)
                                ->color('primary')
                                ->copyable()
                                ->icon(\Filament\Support\Icons\Heroicon::Hashtag),
                            \Filament\Infolists\Components\TextEntry::make('status')
                                ->label('TRANSIT STATUS')
                                ->badge(),
                            \Filament\Infolists\Components\TextEntry::make('productVariant.sku')
                                ->label('SKU')
                                ->fontFamily('mono')
                                ->copyable(),
                            \Filament\Infolists\Components\TextEntry::make('productVariant.name')
                                ->label('VARIANT NAME'),
                        ]),
                    ])->columnSpan(2),

                    \Filament\Schemas\Components\Section::make('QUANTITY TRACKING')
                        ->icon(\Filament\Support\Icons\Heroicon::ArrowPath)
                        ->schema([
                            \Filament\Schemas\Components\Grid::make(3)->schema([
                                \Filament\Infolists\Components\TextEntry::make('dispatched_base_qty')
                                    ->label('DISPATCHED (BASE UNITS)')
                                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                                    ->numeric(),
                                \Filament\Infolists\Components\TextEntry::make('transferRequisitionItem.received_good_base_qty')
                                    ->label('RECEIVED GOOD')
                                    ->placeholder('0')
                                    ->numeric(),
                                \Filament\Infolists\Components\TextEntry::make('transferRequisitionItem.received_damaged_base_qty')
                                    ->label('RECEIVED DAMAGED')
                                    ->placeholder('0')
                                    ->numeric(),
                            ]),
                        ])->columnSpan(1),

                    \Filament\Schemas\Components\Section::make('WAREHOUSE ROUTING')
                        ->icon(\Filament\Support\Icons\Heroicon::BuildingOffice)
                        ->schema([
                            \Filament\Schemas\Components\Grid::make(2)->schema([
                                \Filament\Infolists\Components\TextEntry::make('transferRequisition.fromWarehouse.name')
                                    ->label('ORIGIN WAREHOUSE')
                                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                                    ->icon(\Filament\Support\Icons\Heroicon::BuildingOffice)
                                    ->color('primary'),
                                \Filament\Infolists\Components\TextEntry::make('transferRequisition.toWarehouse.name')
                                    ->label('DESTINATION WAREHOUSE')
                                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                                    ->icon(\Filament\Support\Icons\Heroicon::BuildingOffice2)
                                    ->color('success'),
                                \Filament\Infolists\Components\TextEntry::make('dispatched_at')
                                    ->label('DISPATCHED AT')
                                    ->dateTime('M d, Y H:i')
                                    ->placeholder('Not Dispatched')
                                    ->columnSpan(2),
                            ]),
                        ])->columnSpanFull(),
                ]),
        ]);
    }
}