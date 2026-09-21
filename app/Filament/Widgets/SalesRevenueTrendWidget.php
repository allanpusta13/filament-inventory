<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\SalesOrder;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class SalesRevenueTrendWidget extends ChartWidget
{
    protected ?string $heading = 'Sales Revenue Trend (Last 30 Days)';

    protected int|string|array $columnSpan = 'full';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $user = auth()->user();

        if (! ($user?->isAdmin() ?? false) && ! ($user?->isAuditor() ?? false)) {
            return $this->emptyData();
        }

        $firstWarehouseId = optional($user->warehouses->first())?->id;
        $cacheKey = 'sales_revenue_trend_'.$user->id.'_'.$firstWarehouseId;

        return Cache::remember($cacheKey, 300, function () use ($user) {
            return $this->computeChartData($user);
        });
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(context) {
                            return "Revenue: ₱" + context.parsed.y.toFixed(4);
                        }',
                    ],
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Revenue (₱)',
                    ],
                ],
                'x' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Last 30 Days',
                    ],
                ],
            ],
        ];
    }

    private function computeChartData($user): array
    {
        $warehouseIds = $user->warehouses->pluck('id')->toArray();

        if (empty($warehouseIds)) {
            return $this->emptyData();
        }

        $days = 30;
        $startDate = Carbon::now()->subDays($days - 1)->startOfDay();

        $salesOrders = SalesOrder::whereIn('warehouse_id', $warehouseIds)
            ->whereIn('status', [
                'dispatched',
                'completed',
            ])
            ->where('dispatched_at', '>=', $startDate)
            ->with('items')
            ->get();

        $buckets = [];
        for ($i = 0; $i < $days; $i++) {
            $date = Carbon::now()->subDays($days - 1 - $i)->startOfDay();
            $buckets[$date->format('Y-m-d')] = '0.0000';
        }

        foreach ($salesOrders as $order) {
            $dayKey = Carbon::parse($order->dispatched_at)->format('Y-m-d');
            if (! isset($buckets[$dayKey])) {
                continue;
            }

            $dailyRevenue = '0.0000';
            foreach ($order->items as $item) {
                if ($item->dispatched_base_qty > 0 && $item->unit_sale_price_snapshot) {
                    $lineTotal = bcmul((string) $item->dispatched_base_qty, (string) $item->unit_sale_price_snapshot, 4);
                    $dailyRevenue = bcadd($dailyRevenue, $lineTotal, 4);
                }
            }

            $buckets[$dayKey] = bcadd($buckets[$dayKey], $dailyRevenue, 4);
        }

        $labels = array_map(function ($dateStr) {
            return Carbon::parse($dateStr)->format('M j');
        }, array_keys($buckets));

        $data = array_values($buckets);

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Daily Revenue',
                    'data' => $data,
                    'borderColor' => 'rgb(34, 197, 94)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                    'pointBackgroundColor' => 'rgb(34, 197, 94)',
                    'pointBorderColor' => '#ffffff',
                    'pointBorderWidth' => 2,
                    'pointRadius' => 4,
                ],
            ],
        ];
    }

    private function emptyData(): array
    {
        return [
            'labels' => [],
            'datasets' => [
                [
                    'label' => 'Daily Revenue',
                    'data' => [],
                    'borderColor' => 'rgb(34, 197, 94)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
        ];
    }
}
