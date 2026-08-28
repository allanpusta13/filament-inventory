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
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('quantity')
                    ->label('Quantity')
                    ->sortable()
                    ->weight('bold')
                    ->color(function (int $state): string {
                        if ($state <= 0) {
                            return 'danger';
                        }

                        return 'success';
                    })
                    ->formatStateUsing(fn (int $state): string => number_format($state)),
                TextColumn::make('stock_status')
                    ->label('Status')
                    ->state(fn ($record): string => match (true) {
                        $record->quantity <= 0 => 'Out of Stock',
                        default => 'In Stock',
                    })
                    ->badge()
                    ->color(fn ($record): string => match (true) {
                        $record->quantity <= 0 => 'danger',
                        default => 'success',
                    }),
            ])
            ->defaultSort('product.name')
            ->recordActions([])
            ->toolbarActions([]);
    }
}
