<?php

declare(strict_types=1);

namespace App\Filament\Resources\InTransits\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

class InTransitInfolist
{
    public static function configure($schema): object
    {
        return $schema->schema([
            Grid::make(3)->schema([
                Section::make('IN-TRANSIT IDENTITY')
                    ->icon(Heroicon::Truck)
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('transferRequisition.reference_code')
                                ->label('REQUISITION REF')
                                ->weight(FontWeight::Bold)
                                ->color('primary')
                                ->copyable()
                                ->icon(Heroicon::Hashtag),
                            TextEntry::make('status')
                                ->label('TRANSIT STATUS')
                                ->badge(),
                            TextEntry::make('productVariant.sku')
                                ->label('SKU')
                                ->fontFamily('mono')
                                ->copyable(),
                            TextEntry::make('productVariant.name')
                                ->label('VARIANT NAME'),
                        ]),
                    ])->columnSpan(2),

                Section::make('QUANTITY TRACKING')
                    ->icon(Heroicon::ArrowPath)
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('dispatched_base_qty')
                                ->label('DISPATCHED (BASE UNITS)')
                                ->weight(FontWeight::Bold)
                                ->numeric(),
                            TextEntry::make('transferRequisitionItem.received_good_base_qty')
                                ->label('RECEIVED GOOD')
                                ->placeholder('0')
                                ->numeric(),
                            TextEntry::make('transferRequisitionItem.received_damaged_base_qty')
                                ->label('RECEIVED DAMAGED')
                                ->placeholder('0')
                                ->numeric(),
                        ]),
                    ])->columnSpan(1),

                Section::make('WAREHOUSE ROUTING')
                    ->icon(Heroicon::BuildingOffice)
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('transferRequisition.fromWarehouse.name')
                                ->label('ORIGIN WAREHOUSE')
                                ->weight(FontWeight::Bold)
                                ->icon(Heroicon::BuildingOffice)
                                ->color('primary'),
                            TextEntry::make('transferRequisition.toWarehouse.name')
                                ->label('DESTINATION WAREHOUSE')
                                ->weight(FontWeight::Bold)
                                ->icon(Heroicon::BuildingOffice2)
                                ->color('success'),
                            TextEntry::make('dispatched_at')
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
