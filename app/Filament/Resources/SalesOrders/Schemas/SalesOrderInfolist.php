<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesOrders\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

class SalesOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(3)
                    ->columnSpanFull()
                    ->schema([
                        // Section 1: Order Profile (Spans 2 Columns)
                        Section::make('SALES ORDER PROFILE')
                            ->icon(Heroicon::Truck)
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('reference_code')
                                            ->label('REFERENCE CODE')
                                            ->weight(FontWeight::Bold)
                                            ->size('lg')
                                            ->copyable()
                                            ->color('primary'),

                                        TextEntry::make('status')
                                            ->label('OPERATIONAL STATUS')
                                            ->badge(),

                                        TextEntry::make('customer.name')
                                            ->label('CUSTOMER')
                                            ->icon(Heroicon::Users),

                                        TextEntry::make('warehouse.name')
                                            ->label('DISPATCH WAREHOUSE')
                                            ->icon(Heroicon::BuildingOffice2),
                                    ]),
                            ])
                            ->columnSpan(2),

                        // Section 2: Authorization Sign-Offs (Spans 1 Column)
                        Section::make('AUTHORIZATION SIGN-OFFS')
                            ->icon(Heroicon::ShieldCheck)
                            ->schema([
                                TextEntry::make('orderedBy.name')
                                    ->label('ORDERED BY')
                                    ->icon(Heroicon::User)
                                    ->placeholder('System Initialized'),

                                TextEntry::make('dispatchedBy.name')
                                    ->label('DISPATCHED BY')
                                    ->icon(Heroicon::Truck)
                                    ->placeholder('Pending Dispatch'),
                            ])
                            ->columnSpan(1),

                        // Section 3: Line Items (Full Width)
                        Section::make('ORDER LINE ITEMS')
                            ->icon(Heroicon::ClipboardDocumentList)
                            ->schema([
                                RepeatableEntry::make('items')
                                    ->label('')
                                    ->table([
                                        TableColumn::make('PRODUCT VARIANT (SKU)'),
                                        TableColumn::make('UNIT'),
                                        TableColumn::make('RATIO'),
                                        TableColumn::make('QTY'),
                                        TableColumn::make('BASE UNITS'),
                                        TableColumn::make('SALE PRICE'),
                                        TableColumn::make('LINE TOTAL'),
                                        TableColumn::make('LINE NOTES'),
                                    ])
                                    ->schema([
                                        Grid::make(8)
                                            ->schema([
                                                TextEntry::make('productVariant.sku')
                                                    ->label('PRODUCT VARIANT (SKU)')
                                                    ->weight(FontWeight::Bold)
                                                    ->columnSpan(2),

                                                TextEntry::make('unit_name')
                                                    ->label('UNIT')
                                                    ->columnSpan(1),

                                                TextEntry::make('unit_ratio')
                                                    ->label('RATIO')
                                                    ->numeric()
                                                    ->columnSpan(1),

                                                TextEntry::make('qty')
                                                    ->label('QTY')
                                                    ->numeric()
                                                    ->columnSpan(1),

                                                TextEntry::make('base_qty')
                                                    ->label('BASE UNITS')
                                                    ->numeric()
                                                    ->columnSpan(1),

                                                TextEntry::make('unit_sale_price_snapshot')
                                                    ->label('SALE PRICE')
                                                    ->numeric(decimalPlaces: 4)
                                                    ->prefix('₱')
                                                    ->columnSpan(1),

                                                TextEntry::make('line_total')
                                                    ->label('LINE TOTAL')
                                                    ->state(fn ($record) => $record->base_qty * $record->unit_sale_price_snapshot)
                                                    ->numeric(decimalPlaces: 4)
                                                    ->prefix('₱')
                                                    ->weight(FontWeight::Bold)
                                                    ->columnSpan(1),

                                                TextEntry::make('notes')
                                                    ->label('LINE NOTES')
                                                    ->columnSpan(2),
                                            ]),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
