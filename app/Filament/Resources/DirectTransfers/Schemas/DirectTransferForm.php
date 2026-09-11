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
                ->options(fn () => \App\Models\Warehouse::query()->where('is_active', true)->pluck('name', 'id'))
                ->required()
                ->searchable()
                ->preload()
                ->default(fn () => auth()->user()->warehouses()->count() === 1
                    ? auth()->user()->warehouses()->first()->id
                    : null)
                ->prefixIcon(\Filament\Support\Icons\Heroicon::BuildingOffice),

            Select::make('to_warehouse_id')
                ->label('DESTINATION WAREHOUSE')
                ->options(fn () => \App\Models\Warehouse::query()->where('is_active', true)->pluck('name', 'id'))
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
                ->getOptionLabelFromRecordUsing(fn (\App\Models\ProductVariant $v) => "{$v->sku} - {$v->name}"),

            TextInput::make('quantity')
                ->label('BASE UNITS TO TRANSFER')
                ->numeric()
                ->minValue(1)
                ->required()
                ->helperText('Quantity in base units (e.g., pieces, grams).'),

            \Filament\Forms\Components\Textarea::make('notes')
                ->label('AUDIT NOTES')
                ->required()
                ->minLength(15)
                ->placeholder('Provide a clear, descriptive audit explanation (min. 15 characters)...'),
        ];
    }

    /**
     * Step 3: Review & Verify
     */
    public static function getReviewSchema(): array
    {
        return [
            \Filament\Forms\Components\Placeholder::make('review_summary')
                ->label('REVIEW & VERIFY')
                ->content(fn (Get $get) => \view('filament.wizards.direct-transfer-review', ['state' => $get()])),
        ];
    }

    public static function configure(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Wizard::make([
                \Filament\Schemas\Components\Wizard\Step::make('Location Mapping')
                    ->description('Map origin and destination warehouses')
                    ->schema(self::getLocationSchema()),

                \Filament\Schemas\Components\Wizard\Step::make('Stock Allocation')
                    ->description('Select variant, quantity, and add notes')
                    ->schema(self::getAllocationSchema()),

                \Filament\Schemas\Components\Wizard\Step::make('Review & Verify')
                    ->description('Confirm all details before executing')
                    ->schema(self::getReviewSchema()),
            ])
                ->modalWidth(\Filament\Support\Enums\Width::MaxContent)
                ->closeModalByClickingAway(false),
        ]);
    }
}