<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseOrders\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

class PurchaseOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(3)
                    ->columnSpanFull()
                    ->schema([
                        // Section 1: Order Profile (Spans 2 Columns)
                        Section::make('PURCHASE ORDER PROFILE')
                            ->icon(Heroicon::ShoppingCart)
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

                                        TextEntry::make('supplier.name')
                                            ->label('SUPPLIER')
                                            ->icon(Heroicon::BuildingOffice),

                                        TextEntry::make('warehouse.name')
                                            ->label('RECEIVING WAREHOUSE')
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

                                TextEntry::make('receivedBy.name')
                                    ->label('RECEIVED BY')
                                    ->icon(Heroicon::QrCode)
                                    ->placeholder('Pending Receipt'),
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
                                        TableColumn::make('ORDER UNIT'),
                                        TableColumn::make('UNIT RATIO'),
                                        TableColumn::make('ORDER QTY'),
                                        TableColumn::make('BASE UNITS'),
                                        TableColumn::make('UNIT COST PRICE'),
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

                                                TextEntry::make('ordered_unit_name')
                                                    ->label('ORDER UNIT')
                                                    ->columnSpan(1),

                                                TextEntry::make('ordered_unit_ratio')
                                                    ->label('UNIT RATIO')
                                                    ->numeric()
                                                    ->columnSpan(1),

                                                TextEntry::make('ordered_qty')
                                                    ->label('ORDER QTY')
                                                    ->numeric()
                                                    ->columnSpan(1),

                                                TextEntry::make('ordered_base_qty')
                                                    ->label('BASE UNITS')
                                                    ->numeric()
                                                    ->columnSpan(1),

                                                TextEntry::make('unit_cost_price')
                                                    ->label('UNIT COST PRICE')
                                                    ->numeric(decimalPlaces: 4)
                                                    ->prefix('₱')
                                                    ->columnSpan(1),

                                                TextEntry::make('line_total')
                                                    ->label('LINE TOTAL')
                                                    ->state(fn ($record) => $record->ordered_base_qty * $record->unit_cost_price)
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
