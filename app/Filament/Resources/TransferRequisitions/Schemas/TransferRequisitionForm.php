<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferRequisitions\Schemas;

use App\Models\ProductVariantUnitConversion;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class TransferRequisitionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            ...self::getRoutingFields(),
            ...self::getMaterialManifestFields(),
        ]);
    }

    public static function getRoutingFields(): array
    {
        return [
            Section::make(__('resources.transfer_requisitions.sections.routing'))
                ->icon(Heroicon::BuildingOffice)
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2, 'xl' => 2])
                ->schema([
                    Select::make('from_warehouse_id')
                        ->label(__('resources.transfer_requisitions.fields.from_warehouse'))
                        ->prefixIcon(Heroicon::BuildingOffice)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->options(fn () => auth()->user()->warehouses()->pluck('name', 'id'))
                        ->default(fn () => auth()->user()->warehouses()->count() === 1
                            ? auth()->user()->warehouses()->first()->id
                            : null)
                        ->required(),

                    Select::make('to_warehouse_id')
                        ->label(__('resources.transfer_requisitions.fields.to_warehouse'))
                        ->prefixIcon(Heroicon::BuildingOffice2)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->options(fn () => auth()->user()->warehouses()->pluck('name', 'id'))
                        ->required()
                        ->different('from_warehouse_id'),
                ]),
        ];
    }

    public static function getMaterialManifestFields(): array
    {
        return [
            Repeater::make('items')
                ->relationship()
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                ->schema([
                    Select::make('product_variant_id')
                        ->label(__('resources.transfer_requisitions.fields.variant_sku'))
                        ->relationship('productVariant', 'sku')
                        ->prefixIcon(Heroicon::Tag)
                        ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                        ->searchable()
                        ->preload()
                        ->required()
                        ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                        ->live()
                        ->afterStateUpdated(function ($set) {
                            $set('requested_unit_name', null);
                            $set('requested_unit_ratio', null);
                        }),

                    Select::make('requested_unit_name')
                        ->label(__('resources.transfer_requisitions.fields.unit'))
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
                            $set('requested_unit_ratio', $ratio ?? 1);
                        }),

                    TextInput::make('requested_unit_ratio')
                        ->label(__('resources.transfer_requisitions.fields.ratio_base'))
                        ->hintIcon(Heroicon::InformationCircle)
                        ->hint(__('resources.transfer_requisitions.hints.ratio_auto'))
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->numeric()
                        ->disabled()
                        ->dehydrated()
                        ->required(),

                    TextInput::make('requested_qty')
                        ->label(__('resources.transfer_requisitions.fields.qty'))
                        ->prefixIcon(Heroicon::Hashtag)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->numeric()
                        ->minValue(1)
                        ->required(),
                ])
                ->minItems(1)
                ->required()
                ->dehydrated()
                ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                    $data['requested_base_qty'] = (int) $data['requested_qty'] * (int) $data['requested_unit_ratio'];

                    return $data;
                })
                ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                    $data['requested_base_qty'] = (int) $data['requested_qty'] * (int) $data['requested_unit_ratio'];

                    return $data;
                }),
        ];
    }
}
