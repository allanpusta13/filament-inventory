<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductVariants\Schemas;

use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

class ProductVariantInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(3)
                    ->columnSpanFull()
                    ->schema([
                        // Section 1: Variant Identity Profile (Spans 2 Columns)
                        Section::make('VARIANT IDENTITY PROFILE')
                            ->icon(Heroicon::QrCode)
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('sku')
                                            ->label('SKU CODE')
                                            ->weight(FontWeight::Bold)
                                            ->size('lg')
                                            ->copyable()
                                            ->color('primary')
                                            ->icon(Heroicon::Hashtag),

                                        TextEntry::make('name')
                                            ->label('VARIANT NAME')
                                            ->weight(FontWeight::Bold),

                                        TextEntry::make('product.name')
                                            ->label('PRODUCT FAMILY')
                                            ->icon(Heroicon::Folder),

                                        TextEntry::make('barcode')
                                            ->label('BARCODE / GTIN')
                                            ->placeholder('No GTIN Registered')
                                            ->icon(Heroicon::QrCode),

                                        TextEntry::make('base_unit_name')
                                            ->label('BASE UNIT OF MEASURE'),

                                        TextEntry::make('created_at')
                                            ->label('REGISTERED DATE')
                                            ->dateTime('M d, Y H:i')
                                            ->color('gray'),
                                    ]),
                            ])
                            ->columnSpan(2),

                        // Section 2: Valuation & Safety Thresholds (Spans 1 Column)
                        Section::make('VALUATION & SAFETY')
                            ->icon(Heroicon::CurrencyDollar)
                            ->schema([
                                TextEntry::make('cost_price')
                                    ->label('UNIT COST PRICE')
                                    ->numeric(decimalPlaces: 4)
                                    ->prefix('$')
                                    ->placeholder('$0.0000'),

                                TextEntry::make('sale_price')
                                    ->label('UNIT SELLING PRICE')
                                    ->numeric(decimalPlaces: 4)
                                    ->prefix('$')
                                    ->placeholder('$0.0000'),

                                TextEntry::make('reorder_point')
                                    ->label('SAFETY THRESHOLD')
                                    ->numeric()
                                    ->badge()
                                    ->color('warning'),
                            ])
                            ->columnSpan(1),

                        // Section 3: Variant Image Gallery (Full Width)
                        Section::make('VARIANT IMAGE GALLERY')
                            ->icon(Heroicon::Photo)
                            ->schema([
                                ImageEntry::make('images')
                                    ->label('')
                                    ->extraImgAttributes([
                                        'class' => 'h-32 object-cover rounded-lg border border-zinc-200 dark:border-zinc-800',
                                    ])
                                    ->placeholder('No images uploaded for this SKU.'),
                            ])
                            ->columnSpanFull(),

                        // Section 4: Packaging Unit Conversions (Full Width)
                        Section::make('PACKAGING UNIT CONVERSIONS')
                            ->icon(Heroicon::Cube)
                            ->schema([
                                RepeatableEntry::make('unitConversions')
                                    ->label('')
                                    ->schema([
                                        Grid::make(3)
                                            ->schema([
                                                TextEntry::make('unit_name')
                                                    ->label('PACKAGING FORMAT')
                                                    ->weight(FontWeight::Bold),

                                                TextEntry::make('base_unit_ratio')
                                                    ->label('MULTIPLIER RATIO')
                                                    ->numeric(),

                                                TextEntry::make('is_default_transfer')
                                                    ->label('TRANSFER DEFAULT')
                                                    ->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No')
                                                    ->badge()
                                                    ->color(fn ($state) => $state ? 'success' : 'gray'),
                                            ]),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
