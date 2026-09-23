<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseOrders\Schemas;

use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\Warehouse;
use Filament\Forms\Components\Placeholder;
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
        return $schema->components([
            Section::make()
                ->schema([
                    // Steps are handled by CreatePurchaseOrder page using HasWizard trait
                    // just provides schemas step
                ]),
        ]);
    }

    /**
     * Step 1: Supplier & Warehouse
     */
    public static function getRoutingSchema(): array
    {
        return [
            Select::make('supplier_id')
                ->label(__('SUPPLIER'))
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
                ->label(__('RECEIVING WAREHOUSE'))
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

    /**
     * Step 2: Line Items
     */
    public static function getItemsSchema(): array
    {
        return [
            Repeater::make('items')
                ->label(__('PURCHASE ORDER LINES'))
                ->relationship()
                ->schema([
                    Grid::make(6)->schema([
                        Select::make('product_variant_id')
                            ->label(__('PRODUCT VARIANT (SKU)'))
                            ->relationship('productVariant', 'sku')
                            ->getOptionLabelFromRecordUsing(fn (ProductVariant $v) => "{$v->sku} — {$v->name}")
                            ->required()
                            ->searchable()
                            ->preload()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                            ->columnSpan(3),

                        TextInput::make('ordered_unit_name')
                            ->label(__('ORDER UNIT'))
                            ->required()
                            ->columnSpan(2),

                        TextInput::make('ordered_unit_ratio')
                            ->label(__('UNIT RATIO (TO BASE)'))
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->columnSpan(1),

                        TextInput::make('ordered_qty')
                            ->label(__('ORDER QTY'))
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => $set(
                                'ordered_base_qty',
                                (int) $get('ordered_qty') * (int) $get('ordered_unit_ratio')
                            ))
                            ->columnSpan(2),

                        TextInput::make('ordered_base_qty')
                            ->label(__('BASE UNITS (COMPUTED)'))
                            ->numeric()
                            ->minValue(1)
                            ->disabled()
                            ->dehydrated()
                            ->columnSpan(1),

                        TextInput::make('unit_cost_price')
                            ->label(__('UNIT COST PRICE'))
                            ->numeric()
                            ->step(0.0001)
                            ->minValue(0)
                            ->required()
                            ->columnSpan(2),

                        Textarea::make('notes')
                            ->label(__('LINE NOTES'))
                            ->columnSpan(6),
                    ])
                        ->columns(6)
                        ->defaultItems(1)
                        ->addActionLabel(__('ADD LINE')),
                ]),
        ];
    }

    /**
     * Step 3: Review & Confirm
     */
    public static function getReviewSchema(): array
    {
        return [
            Toggle::make('update_cost_price')
                ->label(__('UPDATE PRODUCT VARIANT COST PRICES ON RECEIPT'))
                ->helperText(__("If enabled, receiving this PO will set each variant's current cost price to this order's unit cost, if different."))
                ->default(false)
                ->required(),

            Placeholder::make('review_summary')
                ->content(fn (Get $get) => view(
                    'filament.wizards.purchase-order-review',
                    ['state' => $get()],
                )),

            Textarea::make('notes')
                ->label(__('ORDER NOTES'))
                ->columnSpanFull(),
        ];
    }
}
