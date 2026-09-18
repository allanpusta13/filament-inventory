<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('product_id')
                ->relationship('product', 'name')
                ->required()
                ->createOptionForm(fn (Schema $schema) => $schema->components([
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('category')->nullable(),
                ]))
                ->afterStateUpdated(fn (Set $set, ?string $state) => $set('product_id', $state)),
            TextInput::make('sku')->required()->unique(ignoreRecord: true)->maxLength(255),
            TextInput::make('barcode')->nullable()->unique(ignoreRecord: true)->maxLength(255),
            TextInput::make('name')->required(),
            TextInput::make('base_unit_name')->required(),
            TextInput::make('reorder_point')->numeric()->default(0)->required(),
            KeyValue::make('attributes'),
            KeyValue::make('images'),
            Toggle::make('is_active')->default(true),
        ]);
    }
}
