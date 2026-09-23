<?php

declare(strict_types=1);

namespace App\Filament\Resources\Suppliers\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

class SupplierInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->schema([
                        Section::make('SUPPLIER PROFILE')
                            ->icon(Heroicon::BuildingOffice)
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('name')
                                            ->label('NAME')
                                            ->weight(FontWeight::Bold)
                                            ->size('lg')
                                            ->copyable()
                                            ->color('primary'),

                                        TextEntry::make('is_active')
                                            ->label('STATUS')
                                            ->badge()
                                            ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                                            ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive'),

                                        TextEntry::make('contact_person')
                                            ->label('CONTACT PERSON')
                                            ->icon(Heroicon::User)
                                            ->placeholder('Not provided'),

                                        TextEntry::make('phone')
                                            ->label('PHONE')
                                            ->icon(Heroicon::Phone)
                                            ->placeholder('Not provided'),

                                        TextEntry::make('email')
                                            ->label('EMAIL')
                                            ->icon(Heroicon::Envelope)
                                            ->placeholder('Not provided'),

                                        TextEntry::make('address')
                                            ->label('ADDRESS')
                                            ->icon(Heroicon::MapPin)
                                            ->placeholder('Not provided')
                                            ->columnSpanFull(),
                                    ]),
                            ])
                            ->columnSpan(2),

                        Section::make('PURCHASE ORDERS')
                            ->icon(Heroicon::ShoppingCart)
                            ->schema([
                                RepeatableEntry::make('purchaseOrders')
                                    ->label('')
                                    ->table([
                                        TableColumn::make('REFERENCE CODE'),
                                        TableColumn::make('STATUS'),
                                        TableColumn::make('WAREHOUSE'),
                                        TableColumn::make('ORDERED AT'),
                                        TableColumn::make('TOTAL'),
                                    ])
                                    ->schema([
                                        Grid::make(6)
                                            ->schema([
                                                TextEntry::make('reference_code')
                                                    ->label('REFERENCE CODE')
                                                    ->weight(FontWeight::Bold)
                                                    ->copyable()
                                                    ->columnSpan(2),

                                                TextEntry::make('status')
                                                    ->label('STATUS')
                                                    ->badge()
                                                    ->columnSpan(1),

                                                TextEntry::make('warehouse.name')
                                                    ->label('WAREHOUSE')
                                                    ->icon(Heroicon::BuildingOffice2)
                                                    ->columnSpan(1),

                                                TextEntry::make('ordered_at')
                                                    ->label('ORDERED AT')
                                                    ->dateTime()
                                                    ->columnSpan(1),

                                                TextEntry::make('items_sum_base_qty_cost')
                                                    ->label('TOTAL')
                                                    ->state(fn ($record) => $record->items->sum(fn ($item) => $item->ordered_base_qty * $item->unit_cost_price))
                                                    ->numeric(decimalPlaces: 2)
                                                    ->prefix('₹')
                                                    ->weight(FontWeight::Bold)
                                                    ->columnSpan(1),
                                            ]),
                                    ]),
                            ])
                            ->columnSpan(2),
                    ]),
            ]);
    }
}