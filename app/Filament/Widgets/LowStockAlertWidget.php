<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Product;
use App\Traits\DashboardFilterable;
use App\Traits\StockActions;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

final class LowStockAlertWidget extends TableWidget
{
    use DashboardFilterable, StockActions;

    protected static ?string $heading = 'Low Stock Alerts';

    protected static ?int $sort = 10;

    protected static ?string $description = 'Products at or below reorder point — quick receive to restock';

    protected int|string|array $columnSpan = [
        'sm' => 'full',
        'md' => 1,
        'lg' => 7,
    ];

    public function table(Table $table): Table
    {
        $user = auth()->user();

        $query = Product::query()
            ->select('products.*')
            ->join('stock_movements', 'products.id', '=', 'stock_movements.product_id')
            ->groupBy('products.id', 'products.sku', 'products.name', 'products.category', 'products.unit', 'products.reorder_point', 'products.created_at', 'products.updated_at')
            ->havingRaw('SUM(stock_movements.quantity) <= products.reorder_point')
            ->havingRaw('SUM(stock_movements.quantity) >= 0');

        $warehouseIds = $this->getFilterWarehouseIds($user);
        if ($warehouseIds !== null) {
            $query->whereIn('stock_movements.warehouse_id', $warehouseIds);
        }

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->fontFamily('mono')
                    ->copyable(),
                TextColumn::make('reorder_point')
                    ->label('Reorder Point')
                    ->sortable(),
                TextColumn::make('current_stock')
                    ->label('Current Stock')
                    ->state(fn (Product $record): int => (int) $record->stockMovements()->sum('quantity'))
                    ->color(fn (int $state): string => match (true) {
                        $state <= 0 => 'danger',
                        default => 'warning',
                    })
                    ->weight('bold'),
                TextColumn::make('status')
                    ->label('Status')
                    ->state(fn (Product $record): string => match (true) {
                        (int) $record->stockMovements()->sum('quantity') <= 0 => 'Out of Stock',
                        default => 'Low Stock',
                    })
                    ->badge()
                    ->color(fn (Product $record): string => match (true) {
                        (int) $record->stockMovements()->sum('quantity') <= 0 => 'danger',
                        default => 'warning',
                    }),
            ])
            ->recordActions([
                $this->quickReceiveAction(),
            ])
            ->toolbarActions([])
            ->defaultSort('name')
            ->paginated([5, 10, 25])
            ->striped();
    }
}
