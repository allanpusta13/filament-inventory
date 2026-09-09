<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductVariants\Schemas;

use App\Filament\Resources\Products\Schemas\ProductForm;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ProductVariantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Section 1: Parent Product Family Attachment
                Section::make('PRODUCT FAMILY')
                    ->icon(Heroicon::Folder)
                    ->columns(2)
                    ->schema([
                        Select::make('product_id')
                            ->label('PRODUCT FAMILY')
                            ->relationship('product', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->columnSpanFull()
                            ->createOptionForm(ProductForm::getComponents()),
                    ]),
                // Section 2: Physical SKU & Scanner Profile
                Section::make('SKU & SCANNER IDENTIFIERS')
                    ->icon(Heroicon::QrCode)
                    ->columns(2)
                    ->schema([
                        TextInput::make('sku')
                            ->label('VARIANT SKU')
                            ->required()
                            ->unique(ignorable: fn ($record) => $record)
                            ->placeholder('PROD-COF-500G')
                            ->prefixIcon(Heroicon::Hashtag),

                        TextInput::make('barcode')
                            ->label('BARCODE / GTIN')
                            ->unique(ignorable: fn ($record) => $record)
                            ->placeholder('4800123456789'),

                        TextInput::make('name')
                            ->label('VARIANT DISPLAY NAME')
                            ->required()
                            ->placeholder('500g Whole Bean Arabica'),

                        TextInput::make('base_unit_name')
                            ->label('BASE UNIT NAME (NON-DIVISIBLE)')
                            ->required()
                            ->placeholder('gram'),
                    ]),

                // Section 3: Sub-Cent Micro-Pricing & Safety Thresholds
                Section::make('MICRO-PRICING & REORDER THRESHOLD')
                    ->icon(Heroicon::CurrencyDollar)
                    ->columns(3)
                    ->schema([
                        TextInput::make('cost_price')
                            ->label('COST PRICE PER BASE UNIT')
                            ->numeric()
                            ->required()
                            ->default(0.0000)
                            ->step('0.0001')
                            ->prefix('$'),

                        TextInput::make('sale_price')
                            ->label('SALE PRICE PER BASE UNIT')
                            ->numeric()
                            ->required()
                            ->default(0.0000)
                            ->step('0.0001')
                            ->prefix('$'),

                        TextInput::make('reorder_point')
                            ->label('SAFETY REORDER POINT')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->helperText('Minimum base units across warehouses.'),

                        Toggle::make('is_active')
                            ->label('ACTIVE IN CATALOG')
                            ->default(true)
                            ->columnSpanFull(),
                    ]),

                // Section 4: Metadata & Media Attachments
                Section::make('ATTRIBUTES & MEDIA')
                    ->icon(Heroicon::Photo)
                    ->collapsible()
                    ->collapsed(true)
                    ->schema([
                        KeyValue::make('attributes')
                            ->label('CUSTOM ATTRIBUTES')
                            ->keyLabel('PROPERTY')
                            ->valueLabel('VALUE'),

                        FileUpload::make('images')
                            ->label('VARIANT IMAGES')
                            ->multiple()
                            ->directory('variant-images')
                            ->json(),
                    ]),
            ]);
    }
}
