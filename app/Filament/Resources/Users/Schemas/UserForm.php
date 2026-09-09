<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

final class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('USER CREDENTIALS & RBAC SCOPE')
                ->icon(Heroicon::User)
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')
                        ->label('FULL NAME')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Jane Doe'),

                    TextInput::make('email')
                        ->label('EMAIL ADDRESS')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique(ignorable: fn ($record) => $record)
                        ->placeholder('jane.doe@inventory.com'),

                    TextInput::make('password')
                        ->label('PASSWORD')
                        ->password()
                        ->required(fn ($operation) => $operation === 'create')
                        ->dehydrated(fn ($state) => filled($state))
                        ->maxLength(255),

                    Select::make('role')
                        ->label('SYSTEM ACCESS ROLE')
                        ->options(UserRole::class)
                        ->required()
                        ->default(UserRole::WAREHOUSE_STAFF)
                        ->searchable(),

                    Select::make('warehouses')
                        ->label('AUTHORIZED PHYSICAL WAREHOUSES')
                        ->multiple()
                        ->relationship('warehouses', 'name')
                        ->preload()
                        ->searchable()
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
