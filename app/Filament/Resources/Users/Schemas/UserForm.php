<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->schema([
                    TextInput::make('name')
                        ->label('FULL NAME')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('John Doe'),

                    TextInput::make('email')
                        ->label('EMAIL ADDRESS')
                        ->required()
                        ->email()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->placeholder('john@example.com'),

                    TextInput::make('password')
                        ->label('PASSWORD')
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->dehydrated(fn ($state): bool => filled($state))
                        ->password()
                        ->placeholder('********'),

                    Select::make('role')
                        ->label('ROLE')
                        ->required()
                        ->options(collect(UserRole::cases())->mapWithKeys(fn (UserRole $role) => [$role->value => $role->getLabel()])),
                ]),
        ]);
    }
}
