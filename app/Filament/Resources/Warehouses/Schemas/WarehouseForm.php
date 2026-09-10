<?php

declare(strict_types=1);

namespace App\Filament\Resources\Warehouses\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class WarehouseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('PHYSICAL WAREHOUSE PROFILE')
                    ->columns(2)
                    ->columnSpanFull()
                    ->icon(Heroicon::MapPin)
                    ->schema([
                        TextInput::make('code')
                            ->required()
                            ->unique(),
                        TextInput::make('name')
                            ->required(),
                        TextInput::make('location'),
                        Toggle::make('is_active')
                            ->inline(false)
                            ->required(),
                    ]),
            ]);
    }
}