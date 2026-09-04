<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class WarehousesRelationManager extends RelationManager
{
    protected static string $relationship = 'warehouses';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('warehouse_id')
                ->relationship('warehouse', 'name')
                ->required()
                ->searchable()
                ->preload(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('location')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                //
            ])
            ->toolbarActions([
                //
            ]);
    }
}
