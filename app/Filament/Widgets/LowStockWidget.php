<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Product;
use App\Traits\DashboardFilterable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

final class LowStockWidget extends TableWidget
{
    use DashboardFilterable;

    protected static ?string $heading = 'Low Stock Alert';

    protected static ?int $sort = 10;

    public static function canView(): bool
    {
        return in_array(auth()->user()?->role?->value, [
            'admin',
            'branch_manager',
            'warehouse_staff',
        ]);
    }

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $warehouseIds = $this->getFilterWarehouseIds($user);

        return $table
            ->query(
                Product::query()
                    ->select('products.id', 'products.name', 'products.sku', 'products.reorder_point')
                    ->join('product_variants', 'products.id', '=', 'product_variants.product_id')
                    ->join('warehouse_stock', 'product_variants.id', '=', 'warehouse_stock.variant_id')
                    ->when($warehouseIds !== null, fn ($query) => $query->whereIn('warehouse_stock.warehouse_id', $warehouseIds))
                    ->groupBy('products.id', 'products.name', 'products.sku', 'products.reorder_point')
                    ->havingRaw('SUM(warehouse_stock.on_hand_quantity) <= products.reorder_point')
                    ->havingRaw('SUM(warehouse_stock.on_hand_quantity) > 0')
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Product')
                    ->searchable(),
                TextColumn::make('sku')
                    ->label('SKU'),
                TextColumn::make('reorder_point')
                    ->label('Reorder Point'),
                TextColumn::make('stock_quantity')
                    ->label('Current Stock')
                    ->state(fn (Product $record): int => (int) $record->warehouseStock()->sum('on_hand_quantity'))
                    ->color('danger'),
            ])
            ->paginated([10, 25, 50]);
    }
}
