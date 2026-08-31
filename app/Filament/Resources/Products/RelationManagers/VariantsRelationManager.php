<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Variant Details')->schema([
                    TextInput::make('sku')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),
                    TextInput::make('barcode')
                        ->maxLength(255),
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('base_unit_name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('e.g., gram, piece'),
                ])->columns(2),
                Section::make('Pricing')->schema([
                    TextInput::make('cost_price')
                        ->numeric()
                        ->required()
                        ->prefix('₱'),
                    TextInput::make('sale_price')
                        ->numeric()
                        ->required()
                        ->prefix('₱'),
                ])->columns(2),
                Section::make('Attributes')->schema([
                    KeyValue::make('attributes')
                        ->reorderable(),
                ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sku')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('barcode')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('base_unit_name')
                    ->sortable(),
                TextColumn::make('cost_price')
                    ->money('PHP')
                    ->sortable(),
                TextColumn::make('sale_price')
                    ->money('PHP')
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
