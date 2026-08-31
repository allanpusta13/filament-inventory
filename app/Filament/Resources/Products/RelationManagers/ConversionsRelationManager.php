<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class ConversionsRelationManager extends RelationManager
{
    protected static string $relationship = 'unitConversions';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Unit Conversion')->schema([
                    TextInput::make('unit_name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('e.g., Box, Pack, Pallet'),
                    TextInput::make('base_unit_ratio')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->suffix('base units per unit'),
                    Checkbox::make('is_default_purchase')
                        ->label('Default for Purchase/Receiving'),
                    Checkbox::make('is_default_transfer')
                        ->label('Default for Transfers'),
                ])->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('unit_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('base_unit_ratio')
                    ->sortable()
                    ->suffix('x'),
                IconColumn::make('is_default_purchase')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('is_default_transfer')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
