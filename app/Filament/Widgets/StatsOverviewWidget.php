<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Product;
use App\Traits\DashboardFilterable;
use Cache;
use DB;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class StatsOverviewWidget extends BaseWidget
{
    use DashboardFilterable;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = [
        'sm' => 1,
        'md' => 2,
        'lg' => 4,
    ];

    protected function getStats(): array
    {
        $user = auth()->user();
        $warehouseIds = $this->getFilterWarehouseIds($user);

        return [
            Stat::make('Total SKUs', $this->getTotalSkuCount($warehouseIds))
                ->description('Products in catalog')
                ->descriptionIcon('heroicon-m-cube')
                ->color('primary')
                ->chart([10, 5, 8, 3, 7, 2, 9])
                ->extraAttributes([
                    'data-testid' => 'kpi-total-skus',
                ]),
            Stat::make('Total Units on Hand', $this->getTotalStockQuantity($warehouseIds))
                ->description('Units across all warehouses')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->chart([10, 5, 8, 3, 7, 2, 9])
                ->extraAttributes([
                    'data-testid' => 'kpi-total-stock',
                ]),
            Stat::make('Items Below Reorder Point', $this->getLowStockCount($warehouseIds))
                ->description('At or below reorder point')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('success')
                ->extraAttributes([
                    'class' => '',
                    'data-testid' => 'kpi-low-stock',
                ]),
            Stat::make('Zero Stock SKUs', $this->getOutOfStockCount($warehouseIds))
                ->description('Products with zero stock')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('success')
                ->extraAttributes([
                    'class' => '',
                    'data-testid' => 'kpi-out-of-stock',
                ]),
        ];
    }

    private function getTotalSkuCount(?array $warehouseIds): int
    {
        return (int) $this->cachedQuery('total_skus', $warehouseIds, fn (?array $ids) => Product::query()
            ->select('products.id')
            ->join('product_variants', 'products.id', '=', 'product_variants.product_id')
            ->join('warehouse_stock', 'product_variants.id', '=', 'warehouse_stock.variant_id')
            ->when($ids !== null, fn ($query) => $query->whereIn('warehouse_stock.warehouse_id', $ids))
            ->where('warehouse_stock.on_hand_quantity', '>', 0)
            ->distinct()
            ->count());
    }

    private function getTotalStockQuantity(?array $warehouseIds): int
    {
        return (int) $this->cachedQuery('total_stock', $warehouseIds, fn (?array $ids) => DB::table('warehouse_stock')
            ->when($ids !== null, fn ($query) => $query->whereIn('warehouse_id', $ids))
            ->sum('on_hand_quantity'));
    }

    private function getLowStockCount(?array $warehouseIds): int
    {
        return (int) $this->cachedQuery('low_stock', $warehouseIds, fn (?array $ids) => Product::query()
            ->select('products.id')
            ->join('product_variants', 'products.id', '=', 'product_variants.product_id')
            ->join('warehouse_stock', 'product_variants.id', '=', 'warehouse_stock.variant_id')
            ->when($ids !== null, fn ($query) => $query->whereIn('warehouse_stock.warehouse_id', $ids))
            ->groupBy('products.id', 'products.reorder_point')
            ->havingRaw('SUM(warehouse_stock.on_hand_quantity) <= products.reorder_point')
            ->havingRaw('SUM(warehouse_stock.on_hand_quantity) > 0')
            ->count());
    }

    private function getOutOfStockCount(?array $warehouseIds): int
    {
        return (int) $this->cachedQuery('out_of_stock', $warehouseIds, fn (?array $ids) => Product::query()
            ->select('products.id')
            ->join('product_variants', 'products.id', '=', 'product_variants.product_id')
            ->join('warehouse_stock', 'product_variants.id', '=', 'warehouse_stock.variant_id')
            ->when($ids !== null, fn ($query) => $query->whereIn('warehouse_stock.warehouse_id', $ids))
            ->groupBy('products.id')
            ->havingRaw('SUM(warehouse_stock.on_hand_quantity) <= 0')
            ->count());
    }

    private function cachedQuery(string $key, ?array $warehouseIds, callable $callback): mixed
    {
        $scopeKey = $warehouseIds === null ? 'all' : md5(implode(',', $warehouseIds));

        return Cache::remember(
            "dashboard_stats:{$key}:{$scopeKey}",
            300,
            fn () => $callback($warehouseIds),
        );
    }
}
