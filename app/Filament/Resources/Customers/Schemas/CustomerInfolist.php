<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->schema([
                        Section::make(__('CUSTOMER PROFILE'))
                            ->icon(Heroicon::Users)
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('name')
                                            ->label(__('NAME'))
                                            ->weight(FontWeight::Bold)
                                            ->size('lg')
                                            ->copyable()
                                            ->color('primary'),

                                        TextEntry::make('is_active')
                                            ->label(__('STATUS'))
                                            ->badge()
                                            ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                                            ->formatStateUsing(fn (bool $state): string => $state ? __('Active') : __('Inactive')),

                                        TextEntry::make('contact_person')
                                            ->label(__('CONTACT PERSON'))
                                            ->icon(Heroicon::User)
                                            ->placeholder(__('Not provided')),

                                        TextEntry::make('phone')
                                            ->label(__('PHONE'))
                                            ->icon(Heroicon::Phone)
                                            ->placeholder(__('Not provided')),

                                        TextEntry::make('email')
                                            ->label(__('EMAIL'))
                                            ->icon(Heroicon::Envelope)
                                            ->placeholder(__('Not provided')),

                                        TextEntry::make('address')
                                            ->label(__('ADDRESS'))
                                            ->icon(Heroicon::MapPin)
                                            ->placeholder(__('Not provided'))
                                            ->columnSpanFull(),
                                    ]),
                            ])
                            ->columnSpan(2),

                        Section::make(__('SALES ORDERS'))
                            ->icon(Heroicon::Banknotes)
                            ->schema([
                                RepeatableEntry::make('salesOrders')
                                    ->label('')
                                    ->table([
                                        TableColumn::make(__('REFERENCE CODE')),
                                        TableColumn::make(__('STATUS')),
                                        TableColumn::make(__('WAREHOUSE')),
                                        TableColumn::make(__('ORDERED AT')),
                                        TableColumn::make(__('TOTAL')),
                                    ])
                                    ->schema([
                                        Grid::make(6)
                                            ->schema([
                                                TextEntry::make('reference_code')
                                                    ->label(__('REFERENCE CODE'))
                                                    ->weight(FontWeight::Bold)
                                                    ->copyable()
                                                    ->columnSpan(2),

                                                TextEntry::make('status')
                                                    ->label(__('STATUS'))
                                                    ->badge()
                                                    ->columnSpan(1),

                                                TextEntry::make('warehouse.name')
                                                    ->label(__('WAREHOUSE'))
                                                    ->icon(Heroicon::BuildingOffice2)
                                                    ->columnSpan(1),

                                                TextEntry::make('ordered_at')
                                                    ->label(__('ORDERED AT'))
                                                    ->dateTime()
                                                    ->columnSpan(1),

                                                TextEntry::make('items_sum_base_qty_price')
                                                    ->label(__('TOTAL'))
                                                    ->state(fn ($record) => $record->items->sum(fn ($item) => $item->base_qty * $item->unit_sale_price_snapshot))
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
