<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'md' => 3, 'xl' => 3])->schema([
                Section::make('Identity')
                    ->icon(Heroicon::Identification)
                    ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 2])
                    ->schema([
                        TextEntry::make('product.name')
                            ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2]),
                        TextEntry::make('sku')
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('barcode')
                            ->placeholder('—')
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                    ]),

                Section::make('Pricing')
                    ->icon(Heroicon::CurrencyDollar)
                    ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                    ->columns(['default' => 1, 'md' => 1, 'xl' => 1])
                    ->schema([
                        TextEntry::make('currentPrice.cost_price')
                            ->money(config('app.currency')),
                        TextEntry::make('currentPrice.sale_price')
                            ->money(config('app.currency')),
                    ]),

                Section::make('Unit Conversions')
                    ->icon(Heroicon::Scale)
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('unitConversions')
                            ->schema([
                                Grid::make(['default' => 1, 'md' => 3, 'xl' => 3])->schema([
                                    TextEntry::make('unit_name')
                                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                                    TextEntry::make('base_unit_ratio')
                                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                                    TextEntry::make('is_default_purchase')
                                        ->badge()->boolean()
                                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                                ]),
                            ]),
                    ]),
            ]),
        ]);
    }
}
