<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('resources.users.fields.name'))
                ->prefixIcon(Heroicon::User)
                ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                ->required()->maxLength(255),

            TextInput::make('email')
                ->label(__('resources.users.fields.email'))
                ->prefixIcon(Heroicon::Envelope)
                ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                ->email()->required()->unique(ignoreRecord: true),

            TextInput::make('password')
                ->label(__('resources.users.fields.password'))
                ->prefixIcon(Heroicon::Key)
                ->password()->revealable()
                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                ->dehydrated(fn ($state) => filled($state))
                ->required(fn (string $operation) => $operation === 'create'),

            Select::make('role')
                ->label(__('resources.users.fields.role'))
                ->prefixIcon(Heroicon::ShieldCheck)
                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                ->options(\App\Enums\UserRole::class)
                ->required(),

            Select::make('warehouses')
                ->label(__('resources.users.fields.warehouses'))
                ->relationship('warehouses', 'name')
                ->prefixIcon(Heroicon::BuildingOffice)
                ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                ->multiple()->searchable()->preload()
                ->helperText(__('resources.users.help.warehouses')),

            Toggle::make('is_active')
                ->label(__('resources.users.fields.is_active'))
                ->onIcon(Heroicon::CheckCircle)
                ->offIcon(Heroicon::XCircle)
                ->columnSpanFull()
                ->default(true),
        ]);
    }
}
