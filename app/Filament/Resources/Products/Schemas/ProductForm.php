<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('PRODUCT FAMILY PROFILE')
                ->icon(Heroicon::ClipboardDocumentList)
                ->columns(2)
                ->columnSpanFull()
                ->schema(self::getComponents()),
        ]);
    }

    /**
     * Reusable array of form field components
     */
    public static function getComponents(): array
    {
        return [
            TextInput::make('name')
                ->label('PRODUCT FAMILY NAME')
                ->required()
                ->placeholder('Specialty Coffee Family'),

            TextInput::make('category')
                ->label('CATEGORY')
                ->placeholder('Beverage Ingredients'),
        ];
    }
}
