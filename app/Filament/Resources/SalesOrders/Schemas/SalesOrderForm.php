<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesOrders\Schemas;

use App\Models\ProductVariantUnitConversion;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class SalesOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            ...self::getCustomerWarehouseFields(),
            ...self::getLineItemsFields(),
        ]);
    }

    public static function getCustomerWarehouseFields(): array
    {
        return [
            Section::make('CUSTOMER & WAREHOUSE')
                ->icon(Heroicon::UserGroup)
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2, 'xl' => 2])
                ->schema([
                    Select::make('customer_id')
                        ->label(__('resources.sales_orders.fields.customer'))
                        ->relationship('customer', 'name')
                        ->prefixIcon(Heroicon::UserGroup)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->searchable()->preload()->required()
                        ->createOptionForm(fn (Schema $schema) => \App\Filament\Resources\Customers\Schemas\CustomerForm::configure($schema)),

                    Select::make('warehouse_id')
                        ->label(__('resources.sales_orders.fields.dispatching_warehouse'))
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
                        ->label(__('resources.sales_orders.fields.variant_sku'))
                        ->relationship('productVariant', 'sku')
                        ->prefixIcon(Heroicon::Tag)
                        ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                        ->searchable()->preload()->required()
                        ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                        ->live()
                        ->afterStateUpdated(function (Get $get, $set, $state) {
                            $set('unit_name', null);
                            $set('unit_ratio', null);
                            $variant = \App\Models\ProductVariant::with('currentPrice')->find($state);
                            $set('_current_sale_price_preview', $variant?->currentPrice?->sale_price ?? '0.0000');
                        }),

                    Select::make('unit_name')
                        ->label(__('resources.sales_orders.fields.unit'))
                        ->prefixIcon(Heroicon::Scale)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->options(function (Get $get) {
                            $variantId = $get('product_variant_id');
                            if (! $variantId) {
                                return [];
                            }

                            return ProductVariantUnitConversion::where('product_variant_id', $variantId)
                                ->orderByDesc('base_unit_ratio')
                                ->pluck('unit_name', 'unit_name')
                                ->toArray();
                        })
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Get $get, $set, $state) {
                            $ratio = ProductVariantUnitConversion::where('product_variant_id', $get('product_variant_id'))
                                ->where('unit_name', $state)
                                ->value('base_unit_ratio');
                            $set('unit_ratio', $ratio ?? 1);
                        }),

                    TextInput::make('unit_ratio')
                        ->label(__('resources.sales_orders.fields.ratio_base'))
                        ->hintIcon(Heroicon::InformationCircle)
                        ->hint(__('resources.sales_orders.hints.ratio_auto'))
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->numeric()->disabled()->dehydrated()->required(),

                    TextInput::make('qty')
                        ->label(__('resources.sales_orders.fields.qty'))
                        ->prefixIcon(Heroicon::Hashtag)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->numeric()->minValue(1)->required(),

                    Placeholder::make('_current_sale_price_preview')
                        ->label(__('resources.sales_orders.fields.catalog_sale_price'))
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->content(fn (Get $get) => $get('_current_sale_price_preview') ?? '—'),
                ])
                ->minItems(1)->required()->dehydrated()
                ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                    $data['base_qty'] = (int) $data['qty'] * (int) $data['unit_ratio'];

                    return $data;
                })
                ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                    $data['base_qty'] = (int) $data['qty'] * (int) $data['unit_ratio'];

                    return $data;
                }),
        ];
    }
}
