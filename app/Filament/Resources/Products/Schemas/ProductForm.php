<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Product form — §7A.1 canonical contract.
 *
 * Three tabs: Identity, Stock & Pricing, Status. Every label resolves
 * through `resources.products.*` per §0A.5.
 */
class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()
                ->persistTabInQueryString()
                ->columnSpanFull()
                ->tabs([
                    Tab::make(__('resources.products.tabs.identity'))
                        ->icon(Heroicon::Identification)
                        ->columns(['default' => 1, 'md' => 2, 'xl' => 2])
                        ->schema([
                            Select::make('product_id')
                                ->label(__('resources.products.fields.product_family'))
                                ->relationship('product', 'name')
                                ->prefixIcon(Heroicon::FolderOpen)
                                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                                ->required()
                                ->createOptionForm(fn (Schema $schema) => $schema->components([
                                    TextInput::make('name')
                                        ->label(__('resources.products.fields.family_name'))
                                        ->prefixIcon(Heroicon::Identification)
                                        ->columnSpanFull()
                                        ->required()
                                        ->maxLength(255),
                                    TextInput::make('category')
                                        ->label(__('resources.products.fields.family_category'))
                                        ->prefixIcon(Heroicon::Tag)
                                        ->columnSpanFull(),
                                ])),

                            TextInput::make('sku')
                                ->label(__('resources.products.fields.sku'))
                                ->prefixIcon(Heroicon::Tag)
                                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                                ->required()
                                ->unique(ignoreRecord: true),

                            TextInput::make('barcode')
                                ->label(__('resources.products.fields.barcode'))
                                ->prefixIcon(Heroicon::QrCode)
                                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                                ->nullable()
                                ->dehydrateStateUsing(fn ($state) => filled($state) ? $state : null)
                                ->unique(ignoreRecord: true),

                            TextInput::make('name')
                                ->label(__('resources.products.fields.variant_name'))
                                ->prefixIcon(Heroicon::Identification)
                                ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                                ->required(),

                            FileUpload::make('images')
                                ->label(__('resources.products.fields.images'))
                                ->columnSpanFull()
                                ->multiple()
                                ->image(),
                        ]),

                    Tab::make(__('resources.products.tabs.stock_pricing'))
                        ->icon(Heroicon::CurrencyDollar)
                        ->columns(['default' => 1, 'md' => 2, 'xl' => 2])
                        ->schema([
                            TextInput::make('base_unit_name')
                                ->label(__('resources.products.fields.base_unit_name'))
                                ->prefixIcon(Heroicon::Scale)
                                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                                ->required(),

                            TextInput::make('reorder_point')
                                ->label(__('resources.products.fields.reorder_point'))
                                ->prefixIcon(Heroicon::ExclamationTriangle)
                                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                                ->numeric()
                                ->default(0)
                                ->required(),

                            KeyValue::make('attributes')
                                ->label(__('resources.products.fields.attributes'))
                                ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2]),
                        ]),

                    Tab::make(__('resources.products.tabs.status'))
                        ->icon(Heroicon::CheckCircle)
                        ->schema([
                            Toggle::make('is_active')
                                ->label(__('resources.products.fields.is_active'))
                                ->onIcon(Heroicon::CheckCircle)
                                ->offIcon(Heroicon::XCircle)
                                ->columnSpanFull()
                                ->default(true),
                        ]),
                ]),
        ]);
    }
}
