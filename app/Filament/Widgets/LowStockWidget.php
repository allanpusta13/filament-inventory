<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

final class LowStockWidget extends TableWidget
{
    protected static ?string $heading = 'Low Stock Alert';

    protected static ?int $sort = 10;

    public static function canView(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Product::query()
                    ->select('products.*')
                    ->join('product_variants', 'products.id', '=', 'product_variants.product_id')
                    ->join('stock_movements', 'product_variants.id', '=', 'stock_movements.variant_id')
                    ->groupBy('products.id')
                    ->havingRaw('SUM(stock_movements.quantity) <= products.reorder_point')
                    ->havingRaw('SUM(stock_movements.quantity) > 0')
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Product')
                    ->searchable(),
                TextColumn::make('sku')
                    ->label('SKU'),
                TextColumn::make('reorder_point')
                    ->label('Reorder Point'),
                TextColumn::make('current_stock')
                    ->label('Current Stock')
                    ->state(function (Product $record): int {
                        return (int) $record->stockMovements()->sum('quantity');
                    })
                    ->color('danger'),
            ])
            ->paginated([10, 25, 50]);
    }
}
