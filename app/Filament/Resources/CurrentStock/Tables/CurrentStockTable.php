<?php

declare(strict_types=1);

namespace App\Filament\Resources\CurrentStock\Tables;

use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class CurrentStockTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->with([
                'variant.product',
                'warehouse',
            ]))
            ->columns([
                TextColumn::make('variant.product.name')
                    ->label('Product')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('variant.sku')
                    ->label('SKU')
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
                    })
                    ->summarize(Sum::make()->label('Total')),
            ])
            ->defaultSort('variant.product.name')
            ->recordActions([])
            ->toolbarActions([]);
    }
}
