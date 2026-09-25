<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('resources.customers.fields.name'))
                ->prefixIcon(Heroicon::UserGroup)
                ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                ->required()->maxLength(255),

            TextInput::make('contact_person')
                ->label(__('resources.customers.fields.contact_person'))
                ->prefixIcon(Heroicon::User)
                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                ->maxLength(255),

            TextInput::make('phone')
                ->label(__('resources.customers.fields.phone'))
                ->prefixIcon(Heroicon::Phone)
                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                ->tel(),

            TextInput::make('email')
                ->label(__('resources.customers.fields.email'))
                ->prefixIcon(Heroicon::Envelope)
                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                ->email(),

            Textarea::make('address')
                ->label(__('resources.customers.fields.address'))
                ->prefixIcon(Heroicon::MapPin)
                ->columnSpanFull(),

            Toggle::make('is_active')
                ->label(__('resources.customers.fields.is_active'))
                ->onIcon(Heroicon::CheckCircle)
                ->offIcon(Heroicon::XCircle)
                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                ->default(true),
        ]);
    }
}
