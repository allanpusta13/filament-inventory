<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Product;
use App\Models\StockMovement;
use App\Traits\DashboardFilterable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class StatsOverviewWidget extends BaseWidget
{
    use DashboardFilterable;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = [
        'sm' => 'full',
        'md' => 'full',
        'lg' => 'full',
    ];

    protected function getStats(): array
    {
        $user = auth()->user();
        $warehouseIds = $this->getFilterWarehouseIds($user);

        $totalItems = $this->getTotalSkuCount($warehouseIds);
        $totalStock = $this->getTotalStockQuantity($warehouseIds);
        $lowStockCount = $this->getLowStockCount($user);
        $outOfStockCount = $this->getOutOfStockCount($user);
        $stockTrend = $this->getStockTrendData($warehouseIds);

        return [
            Stat::make('Total Active SKUs', number_format($totalItems))
                ->description('Products in catalog')
                ->descriptionIcon('heroicon-m-cube')
                ->color('primary')
                ->chart($stockTrend)
                ->extraAttributes([
                    'data-testid' => 'kpi-total-skus',
                ]),
            Stat::make('Total Stock Quantity', number_format($totalStock))
                ->description('Units across all warehouses')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->chart($stockTrend)
                ->extraAttributes([
                    'data-testid' => 'kpi-total-stock',
                ]),
            Stat::make('Items Below Reorder Point', number_format($lowStockCount))
                ->description('At or below reorder point')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($lowStockCount > 0 ? 'warning' : 'success')
                ->descriptionColor($lowStockCount > 0 ? 'warning' : null)
                ->extraAttributes([
                    'class' => $lowStockCount > 0 ? 'ring-1 ring-amber-400/30 bg-amber-50/50 dark:bg-amber-950/20' : '',
                    'data-testid' => 'kpi-low-stock',
                ]),
            Stat::make('Zero Stock SKUs', number_format($outOfStockCount))
                ->description('Products with zero stock')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($outOfStockCount > 0 ? 'danger' : 'success')
                ->descriptionColor($outOfStockCount > 0 ? 'danger' : null)
                ->extraAttributes([
                    'class' => $outOfStockCount > 0 ? 'ring-1 ring-rose-500/30 bg-rose-50/50 dark:bg-rose-950/20' : '',
                    'data-testid' => 'kpi-out-of-stock',
                ]),
        ];
    }

    private function getTotalSkuCount(?array $warehouseIds): int
    {
        $query = Product::query();

        if ($warehouseIds !== null) {
            $query->whereIn('id', function ($query) use ($warehouseIds) {
                $query->select('product_id')
                    ->from('stock_movements')
                    ->whereIn('warehouse_id', $warehouseIds)
                    ->groupBy('product_id');
            });
        }

        return $query->count();
    }

    private function getTotalStockQuantity(?array $warehouseIds): int
    {
        $query = StockMovement::query()
            ->selectRaw('COALESCE(SUM(quantity), 0) as total_quantity');

        if ($warehouseIds !== null) {
            $query->whereIn('warehouse_id', $warehouseIds);
        }

        return (int) $query->value('total_quantity');
    }

    private function getStockTrendData(?array $warehouseIds): array
    {
        $query = StockMovement::query()
            ->selectRaw('DATE(created_at) as date, COALESCE(SUM(quantity), 0) as daily_total')
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date');

        if ($warehouseIds !== null) {
            $query->whereIn('warehouse_id', $warehouseIds);
        }

        $data = $query->pluck('daily_total', 'date')->toArray();

        $result = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $result[] = (int) ($data[$date] ?? 0);
        }

        return $result;
    }

    private function getLowStockCount(\App\Models\User $user): int
    {
        $query = Product::query()
            ->select('products.id')
            ->join('stock_movements', 'products.id', '=', 'stock_movements.product_id')
            ->groupBy('products.id', 'products.reorder_point')
            ->havingRaw('SUM(stock_movements.quantity) <= products.reorder_point')
            ->havingRaw('SUM(stock_movements.quantity) > 0');

        $warehouseIds = $this->getFilterWarehouseIds($user);
        if ($warehouseIds !== null) {
            $query->whereIn('stock_movements.warehouse_id', $warehouseIds);
        }

        return $query->count();
    }

    private function getOutOfStockCount(\App\Models\User $user): int
    {
        $query = Product::query()
            ->select('products.id')
            ->join('stock_movements', 'products.id', '=', 'stock_movements.product_id')
            ->groupBy('products.id')
            ->havingRaw('SUM(stock_movements.quantity) <= 0');

        $warehouseIds = $this->getFilterWarehouseIds($user);
        if ($warehouseIds !== null) {
            $query->whereIn('stock_movements.warehouse_id', $warehouseIds);
        }

        return $query->count();
    }
}
