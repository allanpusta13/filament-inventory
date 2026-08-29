<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Product;
use App\Models\StockMovement;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = [
        'sm' => 'full',
        'md' => 'full',
        'lg' => 'full',
    ];

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user?->isAdmin() ?? false;
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $isAdmin = $user->isAdmin();

        $totalItems = Product::count();

        $stockQuery = StockMovement::query()
            ->selectRaw('SUM(quantity) as total_quantity');

        if (! $isAdmin) {
            $warehouseIds = $user->warehouses()->pluck('warehouses.id');
            $stockQuery->whereIn('warehouse_id', $warehouseIds);
        }

        $totalStock = (int) $stockQuery->value('total_quantity');

        $lowStockCount = $this->getLowStockCount($isAdmin, $user);
        $outOfStockCount = $this->getOutOfStockCount($isAdmin, $user);

        return [
            Stat::make('Total SKUs', number_format($totalItems))
                ->description('Products in catalog')
                ->descriptionIcon('heroicon-m-cube')
                ->color('primary')
                ->chart([7, 3, 4, 5, 6, 3, 5, 2]),
            Stat::make('Total Units on Hand', number_format($totalStock))
                ->description('Units across all warehouses')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make('Items Below Reorder Point', number_format($lowStockCount))
                ->description('At or below reorder point')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($lowStockCount > 0 ? 'warning' : 'success')
                ->extraAttributes([
                    'class' => $lowStockCount > 0 ? 'ring-1 ring-amber-400/20' : '',
                ]),
            Stat::make('Zero Stock SKUs', number_format($outOfStockCount))
                ->description('Products with zero stock')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($outOfStockCount > 0 ? 'danger' : 'success')
                ->extraAttributes([
                    'class' => $outOfStockCount > 0 ? 'ring-1 ring-rose-500/20' : '',
                ]),
        ];
    }

    private function getLowStockCount(bool $isAdmin, $user): int
    {
        $query = Product::query()
            ->select('products.id')
            ->join('stock_movements', 'products.id', '=', 'stock_movements.product_id')
            ->groupBy('products.id', 'products.reorder_point')
            ->havingRaw('SUM(stock_movements.quantity) <= products.reorder_point')
            ->havingRaw('SUM(stock_movements.quantity) > 0');

        if (! $isAdmin) {
            $warehouseIds = $user->warehouses()->pluck('warehouses.id');
            $query->whereIn('stock_movements.warehouse_id', $warehouseIds);
        }

        return $query->count();
    }

    private function getOutOfStockCount(bool $isAdmin, $user): int
    {
        $query = Product::query()
            ->select('products.id')
            ->join('stock_movements', 'products.id', '=', 'stock_movements.product_id')
            ->groupBy('products.id')
            ->havingRaw('SUM(stock_movements.quantity) <= 0');

        if (! $isAdmin) {
            $warehouseIds = $user->warehouses()->pluck('warehouses.id');
            $query->whereIn('stock_movements.warehouse_id', $warehouseIds);
        }

        return $query->count();
    }
}
