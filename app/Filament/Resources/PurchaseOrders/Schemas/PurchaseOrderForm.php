<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseOrders\Schemas;

use App\Models\ProductVariant;
use App\Models\Supplier;
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

class PurchaseOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('STEP 1: SUPPLIER & WAREHOUSE')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('supplier_id')
                                ->label('SUPPLIER')
                                ->options(fn () => Supplier::query()->where('is_active', true)->pluck('name', 'id'))
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
                                ->prefixIcon(\Filament\Support\Icons\Heroicon::BuildingOffice),

                            Select::make('warehouse_id')
                                ->label('RECEIVING WAREHOUSE')
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

                Section::make('STEP 2: LINE ITEMS')
                    ->schema([
                        Repeater::make('items')
                            ->label('PURCHASE ORDER LINES')
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
                                        ->afterStateUpdated(fn (Set $set, Get $get, ?string $state) => $set('ordered_unit_name', ProductVariant::find($state)?->base_unit_name ?? '')),

                                    TextInput::make('ordered_unit_name')
                                        ->label('ORDER UNIT')
                                        ->required()
                                        ->columnSpan(2),

                                    TextInput::make('ordered_unit_ratio')
                                        ->label('UNIT RATIO')
                                        ->required()
                                        ->numeric()
                                        ->minValue(1)
                                        ->default(1)
                                        ->columnSpan(1),

                                    TextInput::make('ordered_qty')
                                        ->label('ORDER QTY')
                                        ->required()
                                        ->numeric()
                                        ->minValue(1)
                                        ->default(1)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn (Set $set, Get $get) => $set('ordered_base_qty', (int) ($get('ordered_qty') ?? 1) * (int) ($get('ordered_unit_ratio') ?? 1)))
                                        ->columnSpan(2),

                                    TextInput::make('ordered_base_qty')
                                        ->label('BASE UNITS')
                                        ->required()
                                        ->numeric()
                                        ->minValue(1)
                                        ->disabled()
                                        ->dehydrated()
                                        ->columnSpan(1),

                                    TextInput::make('unit_cost_price')
                                        ->label('UNIT COST PRICE')
                                        ->required()
                                        ->numeric()
                                        ->minValue(0)
                                        ->step(0.0001)
                                        ->prefix('₱')
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

                Section::make('STEP 3: REVIEW & UPDATE COST PRICE')
                    ->schema([
                        Toggle::make('update_cost_price')
                            ->label('UPDATE PRODUCT VARIANT COST PRICES ON RECEIPT')
                            ->helperText('When enabled, the cost prices from this purchase order will become the new current cost prices for the variants upon full receipt.')
                            ->default(false)
                            ->required(),

                        Textarea::make('notes')
                            ->label('ORDER NOTES')
                            ->columnSpanFull(),
                    ])->collapsible(),
            ]);
    }
}
