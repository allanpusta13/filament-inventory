<?php

declare(strict_types=1);

namespace App\Filament\Resources\DirectTransfers\Schemas;

use App\Models\ProductVariant;
use App\Models\ProductVariantUnitConversion;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

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
                        ->options(fn () => auth()->user()->warehouses()->pluck('name', 'id'))
                        ->default(fn () => auth()->user()->warehouses()->count() === 1
                            ? auth()->user()->warehouses()->first()->id
                            : null)
                        ->required(),

                    Select::make('to_warehouse_id')
                        ->label(__('resources.direct_transfers.fields.to_warehouse'))
                        ->prefixIcon(Heroicon::BuildingOffice2)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->options(fn () => auth()->user()->warehouses()->pluck('name', 'id'))
                        ->required()
                        ->different('from_warehouse_id'),
                ]),
        ];
    }

    public static function getStockAllocationFields(): array
    {
        return [
            Repeater::make('items')
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                ->schema([
                    Select::make('product_variant_id')
                        ->label(__('resources.direct_transfers.fields.variant_sku'))
                        ->prefixIcon(Heroicon::Tag)
                        ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                        ->options(fn () => ProductVariant::query()
                            ->where('is_active', true)
                            ->orderBy('sku')
                            ->pluck('sku', 'id'))
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($set) {
                            $set('unit_name', null);
                            $set('unit_ratio', null);
                        }),

                    Select::make('unit_name')
                        ->label(__('resources.direct_transfers.fields.unit'))
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
                        ->label(__('resources.direct_transfers.fields.ratio_base'))
                        ->hintIcon(Heroicon::InformationCircle)
                        ->hint(__('resources.direct_transfers.hints.ratio_auto'))
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->numeric()
                        ->disabled()
                        ->dehydrated()
                        ->required(),

                    TextInput::make('qty')
                        ->label(__('resources.direct_transfers.fields.qty'))
                        ->prefixIcon(Heroicon::Hashtag)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->numeric()
                        ->minValue(1)
                        ->required(),
                ])
                ->minItems(1)
                ->required()
                ->dehydrated(),

            Textarea::make('notes')
                ->label(__('resources.direct_transfers.fields.notes'))
                ->prefixIcon(Heroicon::ChatBubbleBottomCenterText)
                ->columnSpanFull()
                ->required()
                ->minLength(15),
        ];
    }
}
