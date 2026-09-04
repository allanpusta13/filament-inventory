<?php

declare(strict_types=1);

namespace App\Filament\Resources\Warehouses\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class WarehouseStocksRelationManager extends RelationManager
{
    protected static string $relationship = 'warehouseStocks';

    public function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('variant.sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('variant.name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('on_hand_quantity')
                    ->label('On-Hand')
                    ->sortable(),
                TextColumn::make('reserved_quantity')
                    ->label('Reserved')
                    ->sortable(),
                TextColumn::make('available_quantity')
                    ->label('Available')
                    ->sortable()
                    ->color(fn (mixed $state): string => ($state <= 0) ? 'danger' : 'primary')
                    ->extraClass(fn (mixed $state): string => ($state <= 0) ? 'font-bold' : ''),
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
