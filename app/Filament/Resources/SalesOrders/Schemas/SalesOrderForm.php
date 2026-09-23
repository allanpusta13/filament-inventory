<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesOrders\Schemas;

use App\Models\Customer;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class SalesOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('STEP 1: CUSTOMER & WAREHOUSE')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('customer_id')
                                ->label('CUSTOMER')
                                ->options(fn () => Customer::query()->where('is_active', true)->pluck('name', 'id'))
                                ->required()
                                ->searchable()
                                ->preload()
                                ->createOptionForm([
                                    TextInput::make('name')->required(),
                                    TextInput::make('contact_person'),
                                    TextInput::make('phone')->tel(),
                                    TextInput::make('email')->email(),
                                    Textarea::make('address')->columnSpanFull(),
                                    Toggle::make('is_active')->required()->default(true),
                                ])
                                ->prefixIcon(\Filament\Support\Icons\Heroicon::Users),

                            Select::make('warehouse_id')
                                ->label('DISPATCH WAREHOUSE')
                                ->options(fn () => Warehouse::query()->where('is_active', true)->pluck('name', 'id'))
                                ->required()
                                ->searchable()
                                ->preload()
                                ->default(fn () => auth()->user()->warehouses()->count() === 1
                                    ? auth()->user()->warehouses()->first()->id
                                    : null)
                                ->prefixIcon(\Filament\Support\Icons\Heroicon::BuildingOffice2),
                        ]),
                    ])->collapsible(),

                Section::make('STEP 2: LINE ITEMS (READ-ONLY SALE PRICE PREVIEW)')
                    ->schema([
                        Repeater::make('items')
                            ->label('SALES ORDER LINES')
                            ->relationship()
                            ->schema([
                                Grid::make(6)->schema([
                                    Select::make('product_variant_id')
                                        ->label('PRODUCT VARIANT (SKU)')
                                        ->relationship('productVariant', 'sku')
                                        ->getOptionLabelFromRecordUsing(fn (ProductVariant $v) => "{$v->sku} — {$v->name}")
                                        ->required()
                                        ->searchable()
                                        ->preload()
                                        ->columnSpan(3)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn (Set $set, Get $get, ?string $state) => [
                                            $set('unit_name', ProductVariant::find($state)?->base_unit_name ?? ''),
                                            $set('unit_ratio', ProductVariant::find($state)?->unitConversions()->first()?->ratio ?? 1),
                                            $set('unit_sale_price_snapshot', ProductVariant::find($state)?->currentPrice?->price ?? 0),
                                        ]),

                                    TextInput::make('unit_name')
                                        ->label('UNIT')
                                        ->required()
                                        ->columnSpan(2),

                                    TextInput::make('unit_ratio')
                                        ->label('RATIO')
                                        ->required()
                                        ->numeric()
                                        ->minValue(1)
                                        ->default(1)
                                        ->columnSpan(1),

                                    TextInput::make('qty')
                                        ->label('QTY')
                                        ->required()
                                        ->numeric()
                                        ->minValue(1)
                                        ->default(1)
                                        ->live(onBlur: true)
                                        ->afterStateUpdate(fn (Set $set, Get $get) => $set('base_qty', (int) ($get('qty') ?? 1) * (int) ($get('unit_ratio') ?? 1)))
                                        ->columnSpan(2),

                                    TextInput::make('base_qty')
                                        ->label('BASE UNITS')
                                        ->required()
                                        ->numeric()
                                        ->minValue(1)
                                        ->disabled()
                                        ->dehydrated()
                                        ->columnSpan(1),

                                    TextInput::make('unit_sale_price_snapshot')
                                        ->label('SALE PRICE (PREVIEW)')
                                        ->required()
                                        ->numeric()
                                        ->minValue(0)
                                        ->step(0.0001)
                                        ->prefix('₱')
                                        ->disabled()
                                        ->dehydrated()
                                        ->helperText('Price snapped from current variant price at confirm time')
                                        ->columnSpan(2),

                                    Textarea::make('notes')
                                        ->label('LINE NOTES')
                                        ->columnSpanFull(),
                                ]),
                            ])
                            ->columns(6)
                            ->defaultItems(1)
                            ->addActionLabel('ADD LINE'),
                    ])->collapsible(),

                Section::make('STEP 3: REVIEW & NOTES')
                    ->schema([
                        Textarea::make('notes')
                            ->label('ORDER NOTES')
                            ->columnSpanFull(),
                    ])->collapsible(),
            ]);
    }

    public static function getRoutingSchema(): array
    {
        return [
            Select::make('customer_id')
                ->label('CUSTOMER')
                ->options(fn () => Customer::query()->where('is_active', true)->pluck('name', 'id'))
                ->required()
                ->searchable()
                ->preload()
                ->createOptionForm([
                    TextInput::make('name')->required(),
                    TextInput::make('contact_person'),
                    TextInput::make('phone')->tel(),
                    TextInput::make('email')->email(),
                    Textarea::make('address')->columnSpanFull(),
                    Toggle::make('is_active')->required()->default(true),
                ])
                ->prefixIcon(\Filament\Support\Icons\Heroicon::Users),

            Select::make('warehouse_id')
                ->label('DISPATCH WAREHOUSE')
                ->options(fn () => Warehouse::query()->where('is_active', true)->pluck('name', 'id'))
                ->required()
                ->searchable()
                ->preload()
                ->default(fn () => auth()->user()->warehouses()->count() === 1
                    ? auth()->user()->warehouses()->first()->id
                    : null)
                ->prefixIcon(\Filament\Support\Icons\Heroicon::BuildingOffice2),
        ];
    }

    public static function getItemsSchema(): array
    {
        return [
            Repeater::make('items')
                ->label('SALES ORDER LINES')
                ->relationship()
                ->schema([
                    Grid::make(6)->schema([
                        Select::make('product_variant_id')
                            ->label('PRODUCT VARIANT (SKU)')
                            ->relationship('productVariant', 'sku')
                            ->getOptionLabelFromRecordUsing(fn (ProductVariant $v) => "{$v->sku} — {$v->name}")
                            ->required()
                            ->searchable()
                            ->preload()
                            ->columnSpan(3)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, Get $get, ?string $state) => [
                                $set('unit_name', ProductVariant::find($state)?->base_unit_name ?? ''),
                                $set('unit_ratio', ProductVariant::find($state)?->unitConversions()->first()?->ratio ?? 1),
                                $set('unit_sale_price_snapshot', ProductVariant::find($state)?->currentPrice?->price ?? 0),
                            ]),

                        TextInput::make('unit_name')
                            ->label('UNIT')
                            ->required()
                            ->columnSpan(2),

                        TextInput::make('unit_ratio')
                            ->label('RATIO')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->columnSpan(1),

                        TextInput::make('qty')
                            ->label('QTY')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, Get $get) => $set('base_qty', (int) ($get('qty') ?? 1) * (int) ($get('unit_ratio') ?? 1)))
                            ->columnSpan(2),

                        TextInput::make('base_qty')
                            ->label('BASE UNITS')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->disabled()
                            ->dehydrated()
                            ->columnSpan(1),

                        TextInput::make('unit_sale_price_snapshot')
                            ->label('SALE PRICE (PREVIEW)')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(0.0001)
                            ->prefix('₱')
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Price snapped from current variant price at confirm time')
                            ->columnSpan(2),

                        Textarea::make('notes')
                            ->label('LINE NOTES')
                            ->columnSpanFull(),
                    ]),
                ])
                ->columns(6)
                ->defaultItems(1)
                ->addActionLabel('ADD LINE'),
        ];
    }

    public static function getReviewSchema(): array
    {
        return [
            Textarea::make('notes')
                ->label('ORDER NOTES')
                ->columnSpanFull(),
        ];
    }
}
