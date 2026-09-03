<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Product;
use App\Traits\DashboardFilterable;
use App\Traits\StockActions;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

final class ProductCatalogWidget extends TableWidget
{
    use DashboardFilterable, StockActions;

    protected static ?string $heading = 'Product Catalog';

    protected static ?int $sort = 50;

    protected static ?string $description = 'All products with real-time stock levels';

    protected int|string|array $columnSpan = [
        'sm' => 'full',
        'md' => 'full',
        'lg' => 8,
    ];

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $warehouseIds = $this->getFilterWarehouseIds($user);

        $query = Product::query();

        if ($warehouseIds !== null) {
            $query->whereIn('id', function ($q) use ($warehouseIds) {
                $q->select('product_variants.product_id')
                    ->from('stock_movements')
                    ->join('product_variants', 'product_variants.id', '=', 'stock_movements.variant_id')
                    ->whereIn('stock_movements.warehouse_id', $warehouseIds)
                    ->groupBy('product_variants.product_id');
            });
        }

        $query->with([
            'stockMovements' => function ($q) use ($warehouseIds) {
                if ($warehouseIds !== null) {
                    $q->whereIn('warehouse_id', $warehouseIds);
                }
            },
            'stockMovements.warehouse',
        ]);

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('sku')
                    ->label('SKU')
                    ->fontFamily('mono')
                    ->badge()
                    ->color('gray')
                    ->copyable(),
                TextColumn::make('name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->limit(30),
                TextColumn::make('category')
                    ->label('Category')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('current_stock')
                    ->label('Stock Qty')
                    ->state(fn (Product $record): int => (int) $record->stockMovements->sum('quantity'))
                    ->sortable()
                    ->color(fn (int $state, Product $record): string => match (true) {
                        $state <= 0 => 'danger',
                        $state <= $record->reorder_point => 'warning',
                        default => 'success',
                    })
                    ->weight('bold'),
                TextColumn::make('reorder_point')
                    ->label('Reorder At')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('warehouse_location')
                    ->label('Location')
                    ->state(function (Product $record): string {
                        return $record->stockMovements
                            ->pluck('warehouse.name')
                            ->filter()
                            ->unique()
                            ->values()
                            ->implode(', ') ?: 'Unassigned';
                    })
                    ->limit(20),
                TextColumn::make('status')
                    ->label('Status')
                    ->state(function (Product $record): string {
                        $qty = (int) $record->stockMovements->sum('quantity');
                        if ($qty <= 0) {
                            return 'Out of Stock';
                        }
                        if ($qty <= $record->reorder_point) {
                            return 'Low Stock';
                        }

                        return 'In Stock';
                    })
                    ->badge()
                    ->color(fn (Product $record): string => match (true) {
                        (int) $record->stockMovements->sum('quantity') <= 0 => 'danger',
                        (int) $record->stockMovements->sum('quantity') <= $record->reorder_point => 'warning',
                        default => 'success',
                    }),
            ])
            ->recordActions([
                $this->quickReceiveAction(),
            ])
            ->toolbarActions([])
            ->defaultSort('name')
            ->paginated([10, 25, 50])
            ->poll('60s')
            ->striped()
            ->searchable();
    }
}
