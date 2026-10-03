<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\StockMovement;
use App\Models\Warehouse;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

/**
 * RecentMovementsWidget — line chart of daily net movement over 7 days.
 *
 * Visibility: all authenticated users.
 * Cache: 60s.
 * Column span: `['default' => 1, 'md' => 1, 'xl' => 1]`.
 */
class RecentMovementsWidget extends ChartWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = ['default' => 1, 'md' => 1, 'xl' => 1];

    public static function canView(): bool
    {
        return auth()->user() !== null;
    }

    public static function cacheTtl(): int
    {
        return 60;
    }

    public static function cacheKey(int $userId, array $warehouseIds): string
    {
        sort($warehouseIds);

        return 'recent_movements_chart_'.$userId.'_'.md5(implode(',', $warehouseIds));
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
                $buckets = StockMovement::query()
                    ->whereIn('warehouse_id', $warehouseIds)
                    ->where('created_at', '>=', now()->subDays(7))
                    ->selectRaw('CAST(created_at AS DATE) as day, SUM(quantity) as total')
                    ->groupBy('day')
                    ->orderBy('day')
                    ->pluck('total', 'day');

                return [
                    'labels' => $buckets->keys()->all(),
                    'datasets' => [
                        [
                            'label' => __('dashboard.charts.net_movement'),
                            'data' => $buckets->map(fn ($v) => (int) $v)->values()->all(),
                        ],
                    ],
                ];
            },
        );
    }
}
