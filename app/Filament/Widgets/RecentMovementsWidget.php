<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\StockMovement;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class RecentMovementsWidget extends ChartWidget
{
    protected ?string $heading = 'Recent Movements';

    protected int|string|array $columnSpan = 1;

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $user = auth()->user();

        if (! ($user?->can('viewAny', StockMovement::class) ?? false)) {
            return [
                'labels' => [],
                'datasets' => [
                    [
                        'label' => 'Movement Count',
                        'data' => [],
                        'borderColor' => 'rgb(59, 130, 246)',
                        'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                        'fill' => true,
                        'tension' => 0.3,
                    ],
                ],
            ];
        }

        $firstWarehouseId = optional($user->warehouses->first())?->id;
        $cacheKey = 'recent_movements_chart_'.$user->id.'_'.$firstWarehouseId;

        return Cache::remember($cacheKey, 60, function () use ($user) {
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
                    'display' => false,
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(context) {
                            return "Movements: " + context.parsed.y;
                        }',
                    ],
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Movement Count',
                    ],
                    'ticks' => [
                        'stepSize' => 1,
                    ],
                ],
                'x' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Last 7 Days',
                    ],
                ],
            ],
        ];
    }

    private function computeChartData($user): array
    {
        $warehouseIds = $user->warehouses->pluck('id')->toArray();

        if (empty($warehouseIds)) {
            return [
                'labels' => [],
                'datasets' => [
                    [
                        'label' => 'Movement Count',
                        'data' => [],
                        'borderColor' => 'rgb(59, 130, 246)',
                        'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                        'fill' => true,
                        'tension' => 0.3,
                    ],
                ],
            ];
        }

        // Daily buckets over last 7 days for "recent" time window
        $days = 7;
        $startDate = Carbon::now()->subDays($days - 1)->startOfDay();

        $movements = StockMovement::whereIn('warehouse_id', $warehouseIds)
            ->where('created_at', '>=', $startDate)
            ->get();

        // Group by day
        $buckets = [];
        for ($i = 0; $i < $days; $i++) {
            $date = Carbon::now()->subDays($days - 1 - $i)->startOfDay();
            $buckets[$date->format('Y-m-d')] = 0;
        }

        foreach ($movements as $movement) {
            $dayKey = Carbon::parse($movement->created_at)->format('Y-m-d');
            if (isset($buckets[$dayKey])) {
                $buckets[$dayKey]++;
            }
        }

        $labels = array_map(function ($dateStr) {
            return Carbon::parse($dateStr)->format('M j');
        }, array_keys($buckets));

        $data = array_values($buckets);

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Movement Count',
                    'data' => $data,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                    'pointBackgroundColor' => 'rgb(59, 130, 246)',
                    'pointBorderColor' => '#ffffff',
                    'pointBorderWidth' => 2,
                    'pointRadius' => 4,
                ],
            ],
        ];
    }
}
