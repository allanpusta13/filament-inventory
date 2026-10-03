<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Product infolist — §7A.3 canonical contract.
 *
 * Outer `Grid::make(['default' => 1, 'md' => 3, 'xl' => 3])` wrapper per
 * §7M.7 rule 2. Document profile sections occupy columnSpan=2 on md/xl;
 * pricing occupies columnSpan=1.
 */
class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'md' => 3, 'xl' => 3])->schema([
                Section::make(__('resources.products.infolist.identity'))
                    ->icon(Heroicon::Identification)
                    ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 2])
                    ->schema([
                        TextEntry::make('product.name')
                            ->label(__('resources.products.fields.family_name'))
                            ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2]),
                        TextEntry::make('sku')
                            ->label(__('resources.products.fields.sku'))
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('barcode')
                            ->label(__('resources.products.fields.barcode'))
                            ->placeholder(__('common.empty'))
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),

                        ImageEntry::make('images')
                            ->label(__('resources.products.fields.images'))
                            ->columnSpanFull(),

                        KeyValueEntry::make('attributes')
                            ->label(__('resources.products.fields.attributes'))
                            ->columnSpanFull(),
                    ]),

                Section::make(__('resources.products.infolist.pricing'))
                    ->icon(Heroicon::CurrencyDollar)
                    ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                    ->columns(['default' => 1, 'md' => 1, 'xl' => 1])
                    ->schema([
                        TextEntry::make('currentPrice.cost_price')
                            ->label(__('resources.products.fields.cost_price'))
                            ->money(config('app.currency')),
                        TextEntry::make('currentPrice.sale_price')
                            ->label(__('resources.products.fields.sale_price'))
                            ->money(config('app.currency')),
                    ]),

                Section::make(__('resources.products.infolist.unit_conversions'))
                    ->icon(Heroicon::Scale)
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('unitConversions')
                            ->label(__('resources.products.fields.unit_conversions'))
                            ->schema([
                                Grid::make(['default' => 1, 'md' => 3, 'xl' => 3])->schema([
                                    TextEntry::make('unit_name')
                                        ->label(__('resources.products.fields.unit_name'))
                                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                                    TextEntry::make('base_unit_ratio')
                                        ->label(__('resources.products.fields.base_unit_ratio'))
                                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                                    IconEntry::make('is_default_purchase')
                                        ->label(__('resources.products.fields.is_default_purchase'))
                                        ->boolean()
                                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                                ]),
                            ]),
                    ]),
            ]),
        ]);
    }
}
