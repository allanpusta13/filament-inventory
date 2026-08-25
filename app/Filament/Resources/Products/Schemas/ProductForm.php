<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

final class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('sku')
                    ->maxLength(255)
                    ->unique()
                    ->required(),
                TextInput::make('name')
                    ->maxLength(255)
                    ->required(),
                TextInput::make('category')
                    ->maxLength(255),
                TextInput::make('unit')
                    ->maxLength(255)
                    ->default('each')
                    ->required(),
                TextInput::make('reorder_point')
                    ->numeric()
                    ->default(0)
                    ->required(),
            ]);
    }
}
