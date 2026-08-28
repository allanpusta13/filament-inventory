<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Tables;

use App\Models\Product;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sku')
                    ->label('SKU')
                    ->sortable()
                    ->searchable()
                    ->fontFamily('mono')
                    ->copyable()
                    ->weight('bold'),
                TextColumn::make('name')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('category')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('unit')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reorder_point')
                    ->label('Reorder')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total_stock')
                    ->label('Stock')
                    ->state(function (Product $record): int {
                        return $record->totalQuantity();
                    })
                    ->color(function (Product $record): string {
                        $qty = $record->totalQuantity();
                        if ($qty <= 0) {
                            return 'danger';
                        }
                        if ($qty <= $record->reorder_point) {
                            return 'warning';
                        }

                        return 'success';
                    })
                    ->weight('bold')
                    ->sortable(),
                TextColumn::make('stock_status')
                    ->label('Status')
                    ->state(function (Product $record): string {
                        $qty = $record->totalQuantity();
                        if ($qty <= 0) {
                            return 'Out of Stock';
                        }
                        if ($qty <= $record->reorder_point) {
                            return 'Low Stock';
                        }

                        return 'In Stock';
                    })
                    ->badge()
                    ->color(function (Product $record): string {
                        $qty = $record->totalQuantity();
                        if ($qty <= 0) {
                            return 'danger';
                        }
                        if ($qty <= $record->reorder_point) {
                            return 'warning';
                        }

                        return 'success';
                    }),
                TextColumn::make('created_at')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordClasses(function (Product $record): ?string {
                $qty = $record->totalQuantity();
                if ($qty <= 0) {
                    return 'bg-rose-50 dark:bg-rose-500/5';
                }
                if ($qty <= $record->reorder_point) {
                    return 'bg-amber-50 dark:bg-amber-500/5';
                }

                return null;
            })
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
