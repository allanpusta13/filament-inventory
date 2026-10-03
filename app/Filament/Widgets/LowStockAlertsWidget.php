<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\ProductVariant;
use App\Models\Warehouse;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

/**
 * LowStockAlertsWidget — bar chart of variants at/below reorder point.
 *
 * Visibility: Admin, Auditor, WarehouseStaff.
 * Cache: 300s.
 * Column span: `['default' => 1, 'md' => 1, 'xl' => 1]`.
 *
 * [ACCEPTED RISK — KEEP] Data: variants where
 * `availableQuantity <= reorder_point` — warehouse-scoped, 300s cache.
 * The per-variant loop below is the intentional live implementation
 * (see §10 "Widget Caching — [ACCEPTED RISK]"); do not replace it
 * outside the recorded trigger (active variants > ~5,000 or cache-miss
 * > ~1–2s).
 */
class LowStockAlertsWidget extends ChartWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = ['default' => 1, 'md' => 1, 'xl' => 1];

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->isAdmin() || $user->isAuditor() || $user->isWarehouseStaff());
    }

    public static function cacheTtl(): int
    {
        return 300;
    }

    public static function cacheKey(int $userId, array $warehouseIds): string
    {
        sort($warehouseIds);

        return 'low_stock_alerts_chart_'.$userId.'_'.md5(implode(',', $warehouseIds));
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
                if (empty($warehouseIds)) {
                    return [
                        'labels' => [],
                        'datasets' => [
                            [
                                'label' => __('dashboard.charts.available'),
                                'data' => [],
                            ],
                        ],
                    ];
                }

                // Scan every active variant first, filter to low-stock, then cap the
                // rendered chart at 20 rows — the limit applies after the filter,
                // never before it.
                $rows = ProductVariant::query()
                    ->where('is_active', true)
                    ->with('currentPrice')
                    ->get()
                    ->map(fn ($v) => [
                        'sku' => $v->sku,
                        'available' => array_sum(array_map(
                            fn ($wid) => $v->availableQuantity($wid),
                            $warehouseIds,
                        )),
                        'reorder' => (int) $v->reorder_point,
                    ])
                    ->filter(fn ($r) => $r['available'] <= $r['reorder'])
                    ->take(20)
                    ->values();

                return [
                    'labels' => $rows->pluck('sku')->all(),
                    'datasets' => [
                        [
                            'label' => __('dashboard.charts.available'),
                            'data' => $rows->pluck('available')->all(),
                        ],
                    ],
                ];
            },
        );
    }
}
