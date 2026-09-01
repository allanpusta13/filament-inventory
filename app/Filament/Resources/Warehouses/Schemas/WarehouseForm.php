<?php

declare(strict_types=1);

namespace App\Filament\Resources\Warehouses\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

final class WarehouseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->maxLength(10)
                    ->required()
                    ->unique(ignoreRecord: true),
                TextInput::make('name')
                    ->maxLength(255)
                    ->required(),
                TextInput::make('location')
                    ->maxLength(255),
                Toggle::make('is_active')
                    ->default(true)
                    ->required(),
                Select::make('users')
                    ->relationship('users', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->default([]),
            ]);
    }
}
