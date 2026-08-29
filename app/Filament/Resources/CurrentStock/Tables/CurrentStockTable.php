<?php

declare(strict_types=1);

namespace App\Filament\Resources\CurrentStock\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class CurrentStockTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->label('Product')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('quantity')
                    ->label('Quantity')
                    ->sortable()
                    ->color(function (int $state): string {
                        return $state > 0 ? 'success' : ($state < 0 ? 'danger' : 'gray');
                    }),
            ])
            ->defaultSort('product.name')
            ->recordActions([])
            ->toolbarActions([]);
    }
}
