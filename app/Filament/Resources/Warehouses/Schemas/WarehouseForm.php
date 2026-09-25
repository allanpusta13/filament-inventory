<?php

declare(strict_types=1);

namespace App\Filament\Resources\Warehouses\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class WarehouseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('WAREHOUSE PROFILE')
                ->icon(Heroicon::BuildingOffice)
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2, 'xl' => 2])
                ->schema([
                    TextInput::make('code')
                        ->label(__('resources.warehouses.fields.code'))
                        ->prefixIcon(Heroicon::Tag)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->required()->unique(ignoreRecord: true)->maxLength(50)
                        ->helperText(__('resources.warehouses.help.code')),

                    TextInput::make('name')
                        ->label(__('resources.warehouses.fields.name'))
                        ->prefixIcon(Heroicon::Identification)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->required()->maxLength(255),

                    Textarea::make('location')
                        ->label(__('resources.warehouses.fields.location'))
                        ->prefixIcon(Heroicon::MapPin)
                        ->columnSpanFull()->rows(2)->maxLength(500),
                ]),

            Section::make('ACCESS & STATUS')
                ->icon(Heroicon::ShieldCheck)
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2, 'xl' => 2])
                ->schema([
                    Select::make('users')
                        ->label(__('resources.warehouses.fields.users'))
                        ->relationship('users', 'name')
                        ->prefixIcon(Heroicon::UserGroup)
                        ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->disabled()
                        ->dehydrated(false)
                        ->helperText(__('resources.warehouses.help.users_readonly')),

                    Toggle::make('is_active')
                        ->label(__('resources.warehouses.fields.is_active'))
                        ->onIcon(Heroicon::CheckCircle)
                        ->offIcon(Heroicon::XCircle)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->default(true),
                ]),
        ]);
    }
}
