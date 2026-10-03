<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Warehouse;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * TopSellingVariantsWidget — horizontal bar chart, top 10 by dispatched qty.
 *
 * Visibility: Admin, Auditor.
 * Cache: 300s.
 * Column span: `['default' => 1, 'md' => 1, 'xl' => 1]`.
 */
class TopSellingVariantsWidget extends ChartWidget
{
    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = ['default' => 1, 'md' => 1, 'xl' => 1];

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->isAdmin() || $user->isAuditor());
    }

    public static function cacheTtl(): int
    {
        return 300;
    }

    public static function cacheKey(int $userId, array $warehouseIds, string $month): string
    {
        sort($warehouseIds);

        return 'top_selling_variants_'.$userId.'_'.md5(implode(',', $warehouseIds)).'_'.$month;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        // Horizontal bars per the §10 Widget Definitions table (`bar, horizontal`).
        return ['indexAxis' => 'y'];
    }

    protected function getData(): array
    {
        $user = auth()->user();
        $warehouseIds = $user->isAdmin() || $user->isAuditor()
            ? Warehouse::query()->pluck('id')->all()
            : $user->warehouses()->pluck('warehouses.id')->all();
        $month = now()->format('Y-m');

        return Cache::remember(
            static::cacheKey($user->id, $warehouseIds, $month),
            static::cacheTtl(),
            function () use ($warehouseIds) {
                $rows = DB::table('sales_order_items')
                    ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_items.sales_order_id')
                    ->join('product_variants', 'product_variants.id', '=', 'sales_order_items.product_variant_id')
                    ->whereIn('sales_orders.warehouse_id', $warehouseIds)
                    ->whereNotNull('sales_orders.dispatched_at')
                    ->where('sales_orders.dispatched_at', '>=', now()->startOfMonth())
                    ->selectRaw('product_variants.sku as sku, SUM(sales_order_items.dispatched_base_qty) as total')
                    ->groupBy('product_variants.sku')
                    ->orderByDesc('total')
                    ->limit(10)
                    ->get();

                return [
                    'labels' => $rows->pluck('sku')->all(),
                    'datasets' => [
                        [
                            'label' => __('dashboard.charts.dispatched'),
                            'data' => $rows->pluck('total')->map(fn ($v) => (int) $v)->all(),
                        ],
                    ],
                ];
            },
        );
    }
}
