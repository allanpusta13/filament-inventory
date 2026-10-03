<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Warehouse;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * SalesVsPurchasesWidget — grouped bar chart, 6-month window.
 *
 * Visibility: Admin, Auditor.
 * Cache: 300s.
 * Column span: `['default' => 1, 'md' => 2, 'xl' => 2]`.
 */
class SalesVsPurchasesWidget extends ChartWidget
{
    protected static ?int $sort = 6;

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

        return 'sales_vs_purchases_'.$userId.'_'.md5(implode(',', $warehouseIds));
    }

    protected function getType(): string
    {
        return 'bar';
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
                $months = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i)->format('Y-m'));

                // Portable day buckets (`CAST(... AS DATE)` is supported by
                // both MySQL and PostgreSQL); rolled up to months in PHP.
                $rollUp = function ($rows) {
                    $out = [];
                    foreach ($rows as $day => $total) {
                        $month = mb_substr((string) $day, 0, 7);
                        $out[$month] = ($out[$month] ?? 0) + (float) $total;
                    }

                    return $out;
                };

                // `unit_cost_price` is per ordered unit (§2.17:
                // `ordered_qty` × `ordered_unit_ratio` = `ordered_base_qty`),
                // so the per-base-unit value divides by `ordered_unit_ratio`.
                $purchases = $rollUp(DB::table('purchase_order_items')
                    ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
                    ->whereIn('purchase_orders.warehouse_id', $warehouseIds)
                    ->whereNotNull('purchase_orders.received_at')
                    ->where('purchase_orders.received_at', '>=', now()->subMonths(6))
                    ->selectRaw('CAST(purchase_orders.received_at AS DATE) as day, SUM(purchase_order_items.received_base_qty * purchase_order_items.unit_cost_price / NULLIF(purchase_order_items.ordered_unit_ratio, 0)) as total')
                    ->groupBy('day')
                    ->pluck('total', 'day'));

                // `unit_sale_price_snapshot` is per ordered unit (§2.19) —
                // same per-base-unit normalization as `SalesRevenueTrendWidget`.
                $sales = $rollUp(DB::table('sales_order_items')
                    ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_items.sales_order_id')
                    ->whereIn('sales_orders.warehouse_id', $warehouseIds)
                    ->whereNotNull('sales_orders.dispatched_at')
                    ->where('sales_orders.dispatched_at', '>=', now()->subMonths(6))
                    ->selectRaw('CAST(sales_orders.dispatched_at AS DATE) as day, SUM(sales_order_items.dispatched_base_qty * sales_order_items.unit_sale_price_snapshot / NULLIF(sales_order_items.unit_ratio, 0)) as total')
                    ->groupBy('day')
                    ->pluck('total', 'day'));

                return [
                    'labels' => $months->all(),
                    'datasets' => [
                        [
                            'label' => __('dashboard.charts.purchases'),
                            'data' => $months->map(fn ($m) => (float) ($purchases[$m] ?? 0))->all(),
                        ],
                        [
                            'label' => __('dashboard.charts.sales'),
                            'data' => $months->map(fn ($m) => (float) ($sales[$m] ?? 0))->all(),
                        ],
                    ],
                ];
            },
        );
    }
}
