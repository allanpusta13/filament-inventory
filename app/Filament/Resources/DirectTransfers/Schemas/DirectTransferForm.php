<?php

declare(strict_types=1);

namespace App\Filament\Resources\DirectTransfers\Schemas;

use App\Models\ProductVariant;
use App\Models\Warehouse;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;

class DirectTransferForm
{
    /**
     * Step 1: Location Mapping
     */
    public static function getLocationSchema(): array
    {
        return [
            Select::make('from_warehouse_id')
                ->label('ORIGIN WAREHOUSE')
                ->options(fn () => Warehouse::query()->where('is_active', true)->pluck('name', 'id'))
                ->required()
                ->searchable()
                ->preload()
                ->default(fn () => auth()->user()->warehouses()->count() === 1
                    ? auth()->user()->warehouses()->first()->id
                    : null)
                ->prefixIcon(\Filament\Support\Icons\Heroicon::BuildingOffice),

            Select::make('to_warehouse_id')
                ->label('DESTINATION WAREHOUSE')
                ->options(fn () => Warehouse::query()->where('is_active', true)->pluck('name', 'id'))
                ->required()
                ->searchable()
                ->preload()
                ->different('from_warehouse_id')
                ->validationMessages([
                    'different' => 'Destination warehouse cannot match origin warehouse.',
                ])
                ->prefixIcon(\Filament\Support\Icons\Heroicon::BuildingOffice2),
        ];
    }

    /**
     * Step 2: Stock Allocation
     */
    public static function getAllocationSchema(): array
    {
        return [
            Select::make('product_variant_id')
                ->label('PRODUCT VARIANT')
                ->relationship('productVariant', 'sku')
                ->required()
                ->searchable()
                ->preload()
                ->getOptionLabelFromRecordUsing(fn (ProductVariant $v) => "{$v->sku} - {$v->name}"),

            TextInput::make('quantity')
                ->label('BASE UNITS TO TRANSFER')
                ->numeric()
                ->minValue(1)
                ->required()
                ->placeholder('e.g., 100'),

            Textarea::make('notes')
                ->label('AUDIT NOTES')
                ->placeholder('Reason for transfer, approval ref, etc.')
                ->columnSpanFull()
                ->minLength(15)
                ->maxLength(500),
        ];
    }

    /**
     * Step 3: Review & Confirm
     */
    public static function getReviewSchema(): array
    {
        return [
            Placeholder::make('review_summary')
                ->label('REVIEW & VERIFY')
                ->content(fn (Get $get) => view('filament.wizards.direct-transfer-review', [
                    'fromWarehouse' => Warehouse::find($get('from_warehouse_id')),
                    'toWarehouse' => Warehouse::find($get('to_warehouse_id')),
                    'productVariant' => ProductVariant::find($get('product_variant_id')),
                    'quantity' => $get('quantity'),
                    'notes' => $get('notes'),
                ])->render()),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Wizard::make([
                    \Filament\Schemas\Components\Wizard\Step::make('Location Mapping')
                        ->description('Select origin and destination warehouses')
                        ->schema(self::getLocationSchema()),

                    \Filament\Schemas\Components\Wizard\Step::make('Stock Allocation')
                        ->description('Select variant and quantity to transfer')
                        ->schema(self::getAllocationSchema()),

                    \Filament\Schemas\Components\Wizard\Step::make('Review & Confirm')
                        ->description('Verify all details before executing transfer')
                        ->schema(self::getReviewSchema()),
                ])
                    ->columnSpanFull(),
            ]);
    }
}
