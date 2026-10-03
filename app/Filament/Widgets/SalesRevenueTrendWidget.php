<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Warehouse;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * SalesRevenueTrendWidget — line chart of daily sale value over 30 days.
 *
 * Visibility: Admin, Auditor.
 * Cache: 300s.
 * Column span: `['default' => 1, 'md' => 2, 'xl' => 2]`.
 */
class SalesRevenueTrendWidget extends ChartWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = ['default' => 1, 'md' => 2, 'xl' => 2];

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->isAdmin() || $user->isAuditor());
    }

    public static function cacheTtl(): int
    {
        return 300;
    }

    public static function cacheKey(int $userId, array $warehouseIds): string
    {
        sort($warehouseIds);

        return 'sales_revenue_trend_'.$userId.'_'.md5(implode(',', $warehouseIds));
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $user = auth()->user();
        $warehouseIds = $user->isAdmin() || $user->isAuditor()
            ? Warehouse::query()->pluck('id')->all()
            : $user->warehouses()->pluck('warehouses.id')->all();

        return Cache::remember(
            static::cacheKey($user->id, $warehouseIds),
            static::cacheTtl(),
            function () use ($warehouseIds) {
                // `unit_sale_price_snapshot` is per ordered unit (§2.19:
                // `qty` × `unit_ratio` = `base_qty`), so the per-base-unit
                // value divides by `unit_ratio` (`NULLIF` guards a zero ratio).
                $buckets = DB::table('sales_order_items')
                    ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_items.sales_order_id')
                    ->whereIn('sales_orders.warehouse_id', $warehouseIds)
                    ->whereNotNull('sales_orders.dispatched_at')
                    ->where('sales_orders.dispatched_at', '>=', now()->subDays(30))
                    ->selectRaw('CAST(sales_orders.dispatched_at AS DATE) as day, SUM(sales_order_items.dispatched_base_qty * sales_order_items.unit_sale_price_snapshot / NULLIF(sales_order_items.unit_ratio, 0)) as total')
                    ->groupBy('day')
                    ->orderBy('day')
                    ->pluck('total', 'day');

                return [
                    'labels' => $buckets->keys()->all(),
                    'datasets' => [
                        [
                            'label' => __('dashboard.charts.revenue'),
                            'data' => $buckets->map(fn ($v) => (float) $v)->values()->all(),
                        ],
                    ],
                ];
            },
        );
    }
}
