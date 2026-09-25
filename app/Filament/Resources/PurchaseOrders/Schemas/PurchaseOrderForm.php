<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseOrders\Schemas;

use App\Models\ProductVariantUnitConversion;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class PurchaseOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            ...self::getSupplierWarehouseFields(),
            ...self::getLineItemsFields(),
            ...self::getReviewFields(),
        ]);
    }

    public static function getSupplierWarehouseFields(): array
    {
        return [
            Section::make('SUPPLIER & WAREHOUSE')
                ->icon(Heroicon::BuildingStorefront)
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2, 'xl' => 2])
                ->schema([
                    Select::make('supplier_id')
                        ->label(__('resources.purchase_orders.fields.supplier'))
                        ->relationship('supplier', 'name')
                        ->prefixIcon(Heroicon::BuildingStorefront)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->searchable()
                        ->preload()
                        ->required()
                        ->createOptionForm(fn (Schema $schema) => \App\Filament\Resources\Suppliers\Schemas\SupplierForm::configure($schema)),

                    Select::make('warehouse_id')
                        ->label(__('resources.purchase_orders.fields.receiving_warehouse'))
                        ->prefixIcon(Heroicon::BuildingOffice2)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->options(fn () => auth()->user()->warehouses()->pluck('name', 'id'))
                        ->default(fn () => auth()->user()->warehouses()->count() === 1
                            ? auth()->user()->warehouses()->first()->id
                            : null)
                        ->required(),
                ]),
        ];
    }

    public static function getLineItemsFields(): array
    {
        return [
            Repeater::make('items')
                ->relationship()
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                ->schema([
                    Select::make('product_variant_id')
                        ->label(__('resources.purchase_orders.fields.variant_sku'))
                        ->relationship('productVariant', 'sku')
                        ->prefixIcon(Heroicon::Tag)
                        ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                        ->searchable()
                        ->preload()
                        ->required()
                        ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                        ->live()
                        ->afterStateUpdated(function ($set) {
                            $set('ordered_unit_name', null);
                            $set('ordered_unit_ratio', null);
                        }),

                    Select::make('ordered_unit_name')
                        ->label(__('resources.purchase_orders.fields.unit'))
                        ->prefixIcon(Heroicon::Scale)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->options(function (Get $get) {
                            $variantId = $get('product_variant_id');
                            if (! $variantId) {
                                return [];
                            }
                            $query = ProductVariantUnitConversion::where('product_variant_id', $variantId);
                            $flagged = (clone $query)->where('is_default_purchase', true)
                                ->orderByDesc('base_unit_ratio')
                                ->pluck('unit_name', 'unit_name')
                                ->toArray();
                            if (! empty($flagged)) {
                                return $flagged;
                            }

                            return $query->orderByDesc('base_unit_ratio')
                                ->pluck('unit_name', 'unit_name')
                                ->toArray();
                        })
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Get $get, $set, $state) {
                            $ratio = ProductVariantUnitConversion::where('product_variant_id', $get('product_variant_id'))
                                ->where('unit_name', $state)
                                ->value('base_unit_ratio');
                            $set('ordered_unit_ratio', $ratio ?? 1);
                        }),

                    TextInput::make('ordered_unit_ratio')
                        ->label(__('resources.purchase_orders.fields.ratio_base'))
                        ->hintIcon(Heroicon::InformationCircle)
                        ->hint(__('resources.purchase_orders.hints.ratio_auto'))
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->numeric()
                        ->disabled()
                        ->dehydrated()
                        ->required(),

                    TextInput::make('ordered_qty')
                        ->label(__('resources.purchase_orders.fields.qty'))
                        ->prefixIcon(Heroicon::Hashtag)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->numeric()
                        ->minValue(1)
                        ->required(),

                    TextInput::make('unit_cost_price')
                        ->label(__('resources.purchase_orders.fields.unit_cost'))
                        ->prefixIcon(Heroicon::CurrencyDollar)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->numeric()
                        ->step(0.0001)
                        ->minValue(0)
                        ->required(),
                ])
                ->minItems(1)
                ->required()
                ->dehydrated()
                ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                    $data['ordered_base_qty'] = (int) $data['ordered_qty'] * (int) $data['ordered_unit_ratio'];

                    return $data;
                })
                ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                    $data['ordered_base_qty'] = (int) $data['ordered_qty'] * (int) $data['ordered_unit_ratio'];

                    return $data;
                }),
        ];
    }

    public static function getReviewFields(): array
    {
        return [
            Toggle::make('update_cost_price')
                ->label(__('resources.purchase_orders.fields.update_cost_price'))
                ->helperText(__('resources.purchase_orders.help.update_cost_price'))
                ->onIcon(Heroicon::CurrencyDollar)
                ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                ->default(false),
        ];
    }
}
