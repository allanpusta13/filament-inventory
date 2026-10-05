<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesOrders\Schemas;

use App\Models\ProductVariantUnitConversion;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Icon;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Sales order form — §7H.1 canonical contract.
 *
 * Line-item repeater uses `->table([...])` for a compact row layout
 * (deviation from §7O.2's `->columns(...)` — see class docblock).
 *
 * The catalog-price preview is a disabled TextInput whose state is
 * written by the variant select's `afterStateUpdated()` hook — no
 * Livewire component or Placeholder required.
 */
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
            Section::make(__('resources.sales_orders.form.customer_warehouse'))
                ->icon(Heroicon::UserGroup)
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2, 'xl' => 2])
                ->schema([
                    Select::make('customer_id')
                        ->label(__('resources.sales_orders.fields.customer'))
                        ->relationship('customer', 'name')
                        ->prefixIcon(Heroicon::UserGroup)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->searchable()
                        ->preload()
                        ->required()
                        ->createOptionForm(fn (Schema $schema) => \App\Filament\Resources\Customers\Schemas\CustomerForm::configure($schema)),

                    Select::make('warehouse_id')
                        ->label(__('resources.sales_orders.fields.dispatching_warehouse'))
                        ->prefixIcon(Heroicon::BuildingOffice2)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->options(fn () => auth()->user()->isAdmin() || auth()->user()->isAuditor()
                            ? \App\Models\Warehouse::query()->pluck('name', 'id')
                            : auth()->user()->warehouses()->pluck('name', 'id'))
                        ->default(fn () => auth()->user()->warehouses()->count() === 1
                            ? auth()->user()->warehouses()->first()->id
                            : null)
                        ->required(),

                    Textarea::make('notes')
                        ->label(__('resources.sales_orders.fields.notes'))
                        // ->prefixIcon(Heroicon::ChatBubbleBottomCenterText)
                        ->columnSpanFull(),
                ]),
        ];
    }

    public static function getLineItemsFields(): array
    {
        return [
            Repeater::make('items')
                ->relationship()
                ->columnSpanFull()
                ->table([
                    TableColumn::make(__('resources.sales_orders.fields.variant_sku')),
                    TableColumn::make(__('resources.sales_orders.fields.unit')),
                    TableColumn::make(__('resources.sales_orders.fields.ratio_base')),
                    TableColumn::make(__('resources.sales_orders.fields.qty')),
                    TableColumn::make(__('resources.sales_orders.fields.catalog_sale_price')),
                ])
                ->schema([
                    Select::make('product_variant_id')
                        ->hiddenLabel()
                        ->relationship('productVariant', 'sku')
                        ->prefixIcon(Heroicon::Tag)
                        ->searchable()
                        ->preload()
                        ->required()
                        ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                        ->live()
                        ->afterStateUpdated(function (Get $get, $set, $state) {
                            $set('unit_name', null);
                            $set('unit_ratio', null);
                            $variant = \App\Models\ProductVariant::with('currentPrice')->find($state);
                            $set('current_sale_price_preview', $variant?->currentPrice?->sale_price ?? '0.0000');
                        }),

                    Select::make('unit_name')
                        ->hiddenLabel()
                        ->prefixIcon(Heroicon::Scale)
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
                        ->hiddenLabel()
                        ->afterContent(Icon::make(Heroicon::InformationCircle)->tooltip(__('resources.sales_orders.hints.ratio_auto')))
                        ->extraAttributes(['aria-label' => __('resources.sales_orders.fields.ratio_base')])
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->numeric()
                        ->disabled()
                        ->dehydrated()
                        ->required(),

                    TextInput::make('qty')
                        ->hiddenLabel()
                        ->prefixIcon(Heroicon::Hashtag)
                        ->numeric()
                        ->minValue(1)
                        ->required(),

                    TextInput::make('current_sale_price_preview')
                        ->hiddenLabel()
                        ->prefixIcon(Heroicon::CurrencyDollar)
                        ->disabled()
                        ->dehydrated(false)
                        ->default('0.0000'),
                ])
                ->minItems(1)
                ->required()
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
