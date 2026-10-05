<?php

declare(strict_types=1);

namespace App\Filament\Resources\DirectTransfers\Schemas;

use App\Models\ProductVariant;
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
 * Direct transfer form — §7C.1 canonical contract.
 *
 * Line-item repeater uses `->table([...])` for a compact row layout
 * (deviation from §7O.2's `->columns(...)` — see class docblock).
 */
class DirectTransferForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            ...self::getLocationMappingFields(),
            ...self::getStockAllocationFields(),
        ]);
    }

    public static function getLocationMappingFields(): array
    {
        return [
            Section::make(__('resources.direct_transfers.sections.routing'))
                ->icon(Heroicon::BuildingOffice)
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2, 'xl' => 2])
                ->schema([
                    Select::make('from_warehouse_id')
                        ->label(__('resources.direct_transfers.fields.from_warehouse'))
                        ->prefixIcon(Heroicon::BuildingOffice)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->options(fn () => auth()->user()->isAdmin() || auth()->user()->isAuditor()
                            ? \App\Models\Warehouse::query()->pluck('name', 'id')
                            : auth()->user()->warehouses()->pluck('name', 'id'))
                        ->default(fn () => auth()->user()->warehouses()->count() === 1
                            ? auth()->user()->warehouses()->first()->id
                            : null)
                        ->required(),

                    Select::make('to_warehouse_id')
                        ->label(__('resources.direct_transfers.fields.to_warehouse'))
                        ->prefixIcon(Heroicon::BuildingOffice2)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->options(fn () => auth()->user()->isAdmin() || auth()->user()->isAuditor()
                            ? \App\Models\Warehouse::query()->pluck('name', 'id')
                            : auth()->user()->warehouses()->pluck('name', 'id'))
                        ->required()
                        ->different('from_warehouse_id'),
                ]),
        ];
    }

    public static function getStockAllocationFields(): array
    {
        return [
            Section::make(__('resources.direct_transfers.sections.stock_allocation'))
                ->icon(Heroicon::Cube)
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                ->schema([
                    Repeater::make('items')
                        ->columnSpanFull()
                        ->table([
                            TableColumn::make(__('resources.direct_transfers.fields.variant_sku')),
                            TableColumn::make(__('resources.direct_transfers.fields.unit')),
                            TableColumn::make(__('resources.direct_transfers.fields.ratio_base')),
                            TableColumn::make(__('resources.direct_transfers.fields.qty')),
                        ])
                        ->schema([
                            Select::make('product_variant_id')
                                ->hiddenLabel()
                                ->prefixIcon(Heroicon::Tag)
                                ->options(fn () => ProductVariant::query()
                                    ->where('is_active', true)
                                    ->orderBy('sku')
                                    ->pluck('sku', 'id'))
                                ->searchable()
                                ->required()
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                ->live()
                                ->afterStateUpdated(function ($set) {
                                    $set('unit_name', null);
                                    $set('unit_ratio', null);
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
                                ->afterContent(Icon::make(Heroicon::InformationCircle)->tooltip(__('resources.direct_transfers.hints.ratio_auto')))
                                ->extraAttributes(['aria-label' => __('resources.direct_transfers.fields.ratio_base')])
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
                        ])
                        ->minItems(1)
                        ->required(),

                    Textarea::make('notes')
                        ->label(__('resources.direct_transfers.fields.notes'))
                        // ->prefixIcon(Heroicon::ChatBubbleBottomCenterText)
                        ->columnSpanFull()
                        ->required()
                        ->minLength(15),
                ]),
        ];
    }
}
